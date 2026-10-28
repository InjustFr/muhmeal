package cloud.duprat.muhmeal

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class HostsTest {
    private val hosts = Hosts("meals.example.com")

    @Test
    fun theAppAndAccountsHostsStayInTheApp() {
        assertTrue(hosts.opensInApp("meals.example.com"))
        assertTrue(hosts.opensInApp("accounts.mossyleaf.studio"))
    }

    @Test
    fun anyOtherHostOpensTheBrowser() {
        assertFalse(hosts.opensInApp("example.org"))
        assertFalse(hosts.opensInApp("accounts.mossyleaf.studio.example.org"))
        assertFalse(hosts.opensInApp(null))
    }

    @Test
    fun anUnreadableAppAddressNeverMatchesAMissingHost() {
        assertFalse(Hosts(null).opensInApp(null))
        assertFalse(Hosts(null).returnedFromAccounts("accounts.mossyleaf.studio", null))
    }

    @Test
    fun historyIsClearedOnlyWhenComingBackFromAccounts() {
        assertTrue(hosts.returnedFromAccounts("accounts.mossyleaf.studio", "meals.example.com"))
        assertFalse(hosts.returnedFromAccounts("meals.example.com", "meals.example.com"))
        assertFalse(hosts.returnedFromAccounts(null, "meals.example.com"))
        assertFalse(hosts.returnedFromAccounts("accounts.mossyleaf.studio", "accounts.mossyleaf.studio"))
    }
}
