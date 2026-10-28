import { describe, expect, it } from 'vitest';

const files = import.meta.glob('./*/*.json', { eager: true, import: 'default' });

const namespaces = {};
for (const [path, content] of Object.entries(files)) {
    const [, language, namespace] = path.match(/^\.\/([a-z]{2})\/([a-zA-Z-]+)\.json$/);
    namespaces[language] = { ...namespaces[language], [namespace]: content };
}

const keysOf = (value, prefix = '') => Object.entries(value).flatMap(([key, child]) => (
    typeof child === 'object' ? keysOf(child, `${prefix}${key}.`) : [`${prefix}${key}`]
));

describe('messages', () => {
    it('ships English and French', () => {
        expect(Object.keys(namespaces).sort()).toEqual(['en', 'fr']);
    });

    it('has the same namespaces in every language', () => {
        expect(Object.keys(namespaces.fr).sort()).toEqual(Object.keys(namespaces.en).sort());
    });

    it.each(Object.keys(namespaces.en))('has the same keys in English and French for %s', (namespace) => {
        expect(keysOf(namespaces.fr[namespace]).sort()).toEqual(keysOf(namespaces.en[namespace]).sort());
    });
});
