const pad = (value) => String(value).padStart(2, '0');

export function isoDay(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function parseDay(day) {
    const [year, month, date] = day.split('-').map(Number);
    return new Date(year, month - 1, date);
}

export function addDays(day, count) {
    const date = parseDay(day);
    date.setDate(date.getDate() + count);
    return isoDay(date);
}

export function daysBetween(from, to) {
    return Math.round((parseDay(to) - parseDay(from)) / 86_400_000);
}

export function relativeDay(day, today) {
    if (day === null || day === undefined) return null;
    const distance = daysBetween(today, day);
    if (distance === 0) return 'today';
    if (distance === 1) return 'tomorrow';
    if (distance === -1) return 'yesterday';
    if (distance > 1 && distance < 7) return 'weekday';
    return distance < 0 ? 'past' : 'later';
}
