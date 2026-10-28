export const MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];

export function mealTypeAt(date = new Date()) {
    const hour = date.getHours();
    if (hour >= 5 && hour < 11) return 'breakfast';
    if (hour >= 11 && hour < 15) return 'lunch';
    if (hour >= 15 && hour < 18) return 'snack';
    return 'dinner';
}
