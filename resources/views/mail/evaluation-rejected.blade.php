<!DOCTYPE html>
<html><head><meta charset="utf-8"></head><body>
    <p>Your research “{{ $submission->title }}” has completed evaluation by all assigned reviewers.</p>
    <p>This evaluation round is rejected because the average score is below 70%.</p>
    <p style="white-space: pre-line;">{{ $submission->admin_notes }}</p>
    <p>Your research is now editable. Review the feedback, revise your research, and resubmit for a new evaluation round.</p>
    <p><a href="{{ route('submissions.show', $submission) }}">View the evaluation result and research</a></p>
</body></html>
