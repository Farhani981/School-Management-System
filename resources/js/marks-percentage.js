export function marksPercentage(values, maximum, attendance = 'present') {
    const total = Number(maximum);
    if (attendance !== 'present' || !Number.isFinite(total) || total <= 0 || !values.length) return null;
    if (values.some(value => value === null || value === undefined || String(value).trim() === '')) return null;
    const marks = values.map(Number);
    if (marks.some(value => !Number.isFinite(value) || value < 0)) return null;
    const obtained = marks.reduce((sum, value) => sum + value, 0);
    if (obtained > total) return null;
    return Math.round((obtained / total) * 10000) / 100;
}
