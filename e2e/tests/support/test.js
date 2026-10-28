import { expect, test as base } from '@playwright/test';

export const test = base.extend({
    page: async ({ page }, use) => {
        const violations = [];
        await page.exposeFunction('reportPolicyViolation', (violation) => violations.push(violation));
        await page.addInitScript(() => {
            document.addEventListener('securitypolicyviolation', (event) => {
                window.reportPolicyViolation(`${event.violatedDirective} blocked ${event.blockedURI || 'inline'} at ${event.sourceFile}:${event.lineNumber}`);
            });
        });
        await use(page);
        expect(violations, 'content security policy violations').toEqual([]);
    },
});

export { expect };
