<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Identity\UserRepository;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FakeAccounts;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthenticationTest extends WebTestCase
{
    use ActsAsUser;

    public function testSignedOutPagesSendToTheMossyleafAccount(): void
    {
        $client = self::createClient();

        $client->request('GET', '/pantry');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/login');

        $query = $this->authorizeQuery($client);
        self::assertSame(['code', 'muhmeal', 'http://localhost/login/check', 'openid email profile', 'S256'], [$query['response_type'], $query['client_id'], $query['redirect_uri'], $query['scope'], $query['code_challenge_method']]);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22,}$/', $query['state']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $query['code_challenge']);
    }

    public function testANewAccountSignsInAndLandsWhereItWasGoing(): void
    {
        $client = self::createClient();
        $client->request('GET', '/pantry');
        $client->request('GET', '/login');
        $state = $this->authorizeQuery($client)['state'];

        $client->request('GET', '/login/check', ['state' => $state, 'code' => FakeAccounts::code(['sub' => 'account-1', 'email' => 'fern@example.com', 'name' => 'Fern'])]);

        self::assertResponseRedirects('/pantry');
        $client->request('GET', '/pantry');
        self::assertResponseIsSuccessful();
        self::assertSame('Fern', self::getContainer()->get(UserRepository::class)->findByAccountId('account-1')?->displayName());
        self::assertNotNull($client->getCookieJar()->get('REMEMBERME'));
    }

    public function testAKnownAccountSignsInAsTheSameUser(): void
    {
        $client = self::createClient();
        $code = FakeAccounts::code(['sub' => 'account-1', 'email' => 'fern@example.com', 'name' => 'Fern']);
        $client->request('GET', '/login');
        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'code' => $code]);
        $first = self::getContainer()->get(UserRepository::class)->findByAccountId('account-1')?->id()->toBase32();
        $client->getCookieJar()->clear();

        $client->request('GET', '/login');
        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'code' => $code]);

        self::assertResponseRedirects('/');
        self::assertSame($first, self::getContainer()->get(UserRepository::class)->findByAccountId('account-1')?->id()->toBase32());
    }

    public function testAnUnknownStateIsRefused(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => 'forged', 'code' => FakeAccounts::code(['sub' => 'account-1', 'email' => 'fern@example.com'])]);
        self::assertResponseRedirects('/login');
        $client->followRedirect();

        self::assertSame('This sign-in took too long or was opened in another tab, please try again.', Json::string(self::props($client), 'error'));
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testAStateIsUsedOnlyOnce(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');
        $state = $this->authorizeQuery($client)['state'];
        $client->request('GET', '/login/check', ['state' => $state, 'code' => 'not-a-code']);
        $client->followRedirect();
        self::assertSame('Unable to sign in, please try again.', Json::string(self::props($client), 'error'));

        $client->request('GET', '/login/check', ['state' => $state, 'code' => FakeAccounts::code(['sub' => 'account-1', 'email' => 'fern@example.com'])]);
        $client->followRedirect();

        self::assertSame('This sign-in took too long or was opened in another tab, please try again.', Json::string(self::props($client), 'error'));
    }

    public function testAnAccountWithoutAccessSeesWhy(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'error' => 'access_denied']);
        $client->followRedirect();

        self::assertSame('Your mossyleaf account does not have access to muhmeal yet.', Json::string(self::props($client), 'error'));
    }

    public function testAnEmailTakenByAnotherAccountIsExplained(): void
    {
        $client = self::createClient();
        self::createUser('louis@example.com');
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'code' => FakeAccounts::code(['sub' => 'account-2', 'email' => 'louis@example.com'])]);
        $client->followRedirect();

        self::assertSame('An account already uses louis@example.com. Sign in instead.', Json::string(self::props($client), 'error'));
    }

    public function testSigningOutAlsoSignsOutOfTheMossyleafAccount(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');
        $code = FakeAccounts::code(['sub' => 'account-1', 'email' => 'fern@example.com']);
        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'code' => $code]);

        $client->request('POST', '/logout', ['_csrf_token' => $this->logoutToken($client)]);

        self::assertResponseRedirects('https://accounts.test/end-session?client_id=muhmeal&id_token_hint=id.'.$code.'&post_logout_redirect_uri=http%3A%2F%2Flocalhost%2F');
        $client->getCookieJar()->clear();
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testSigningOutOfARememberedSessionStillEndsTheMossyleafSession(): void
    {
        $client = self::createClient();
        $client->loginUser(SecurityUser::fromUser(self::createUser()));

        $client->request('POST', '/logout', ['_csrf_token' => $this->logoutToken($client)]);

        self::assertResponseRedirects('https://accounts.test/end-session?client_id=muhmeal');
    }

    private function logoutToken(KernelBrowser $client): string
    {
        $client->request('GET', '/credits');

        return Json::string(Json::decode((string) $client->getCrawler()->filter('#app-session')->text()), 'logoutToken');
    }

    /**
     * @return array<string, string>
     */
    private function authorizeQuery(KernelBrowser $client): array
    {
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://accounts.test/authorize?', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);

        $strings = [];
        foreach ($query as $name => $value) {
            $strings[(string) $name] = \is_string($value) ? $value : '';
        }

        return $strings;
    }

    /**
     * @return array<mixed>
     */
    private static function props(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getCrawler()->filter('#auth')->attr('data-props'));
    }
}
