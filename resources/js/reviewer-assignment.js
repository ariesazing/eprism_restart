export default function reviewerAssignment(reviewers, initial) {
    return {
        selected: initial.map(String),
        get projection() {
            const before = reviewers.map(r => Number(r.assigned_submissions_count));
            const after = reviewers.map((r, i) => before[i] + Number(this.selected.includes(String(r.id))) - Number(initial.map(String).includes(String(r.id))));
            const meanOf = values => values.reduce((a, b) => a + b, 0) / (values.length || 1);
            const mean = meanOf(after);
            const variance = meanOf(after.map(n => (n - mean) ** 2));
            const deviation = Math.max(0, ...after) - mean;
            const beforeMean = meanOf(before);
            const beforeVariance = meanOf(before.map(n => (n - beforeMean) ** 2));
            const improving = variance < beforeVariance && deviation <= Math.max(0, ...before) - beforeMean;
            return { mean, variance, warning: variance > 1 || deviation > 1, blocked: (variance > 2.25 || deviation > 2) && !improving };
        },
    };
}
