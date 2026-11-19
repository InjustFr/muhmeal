const fold = (text) => text.toLowerCase().normalize('NFD').replace(/\p{M}/gu, '').replace(/œ/g, 'oe').replace(/æ/g, 'ae');

export const nameMatches = (name, query) => fold(name).includes(fold(query.trim()));
