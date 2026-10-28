import { ref } from 'vue';
import { intlLocale } from '../i18n/locale.js';
import { isoDay, parseDay, relativeDay } from '../dates/days.js';

const today = ref(isoDay(new Date()));
window.setInterval(() => { today.value = isoDay(new Date()); }, 60_000);

const formats = new Map();
function format(options) {
    const key = JSON.stringify(options);
    if (!formats.has(key)) formats.set(key, new Intl.DateTimeFormat(intlLocale(), options));
    return formats.get(key);
}

export function useDates() {
    return {
        today,
        weekday: (day) => format({ weekday: 'long' }).format(parseDay(day)),
        short: (day) => format({ weekday: 'short', day: 'numeric', month: 'short' }).format(parseDay(day)),
        long: (day) => format({ weekday: 'long', day: 'numeric', month: 'long' }).format(parseDay(day)),
        dayNumber: (day) => format({ day: 'numeric' }).format(parseDay(day)),
        relative: (day) => relativeDay(day, today.value),
    };
}
