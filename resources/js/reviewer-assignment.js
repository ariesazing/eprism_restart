export default function reviewerAssignment(reviewers, initial) {
    return {
        selected: initial.map(String),
        get projection() {
            const before = reviewers.map(r => Number(r.assigned_submissions_count));
            const after = reviewers.map((r, i) => before[i] + Number(this.selected.includes(String(r.id))) - Number(initial.map(String).includes(String(r.id))));
            const meanOf = values => values.reduce((a, b) => a + b, 0) / (values.length || 1);
            const mean = meanOf(after);
            const maximum = Math.max(0, ...after);
            const spread = after.length ? maximum - Math.min(...after) : 0;
            const excess = maximum * after.length - after.reduce((a, b) => a + b, 0);
            const blocked = after.length > 0 && excess >= 2 * after.length;
            return { mean, deviation: maximum - mean, balanced: spread <= 1, warning: !blocked && excess > after.length, blocked };
        },
    };
}
