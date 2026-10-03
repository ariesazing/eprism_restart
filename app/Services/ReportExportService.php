<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function download(array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            $write = function (array $row) use ($stream) {
                // Treat user-entered titles and names as text in spreadsheet apps.
                $row = array_map(function ($value) {
                    $value = (string) ($value ?? '');
                    return preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
                }, $row);
                fputcsv($stream, $row, ',', '"', '');
            };
            $write(['Section', 'Record', 'Metric', 'Value']);
            $write(['Report', '', 'Organization', 'Schools Division of Santiago City-Research Unit']);
            $write(['Report', '', 'Generated at', now()->toIso8601String()]);
            $write(['Report', '', 'Scope', 'All-time metrics; filters apply to reviewer, approved research, and rejected research records']);

            foreach (['totalSubmissions', 'submissionsByStatus', 'categorization', 'stages', 'submissionTrend', 'byOrganizationalUnit', 'recommendationCounts', 'avgTimeToApproval', 'timeInStatus', 'revisionCycles', 'filters'] as $section) {
                $flatten = function ($value, string $key = '') use (&$flatten, $write, $section) {
                    if (is_iterable($value) || is_object($value)) {
                        foreach ($value as $childKey => $child) {
                            $flatten($child, $key === '' ? (string) $childKey : $key.'.'.$childKey);
                        }
                    } else {
                        $write([$section, '', $key, $value]);
                    }
                };
                $flatten($data[$section]);
            }

            foreach ($data['reviewerLoads'] as $reviewer) {
                foreach (['name', 'email', 'active_assignments_count', 'not_started_count', 'in_progress_count', 'completed_evaluations_count', 'overdue_count'] as $metric) {
                    $write(['Reviewer workload', $reviewer->id, $metric, $reviewer->$metric]);
                }
            }
            foreach ($data['approvedResearch'] as $submission) {
                $record = $submission->reference_code ?: $submission->id;
                foreach (['title', 'research_type', 'classification', 'organizational_unit', 'approved_at'] as $metric) {
                    $value = $submission->$metric;
                    $write(['Approved research', $record, $metric, $value instanceof \BackedEnum ? $value->value : $value]);
                }
                $write(['Approved research', $record, 'researcher', $submission->researcher?->name]);
                $write(['Approved research', $record, 'reviewers', $submission->reviewers->pluck('name')->implode('; ')]);
            }
            foreach ($data['rejectedResearch'] as $submission) {
                $record = $submission->reference_code ?: $submission->id;
                foreach (['title', 'research_type', 'classification', 'organizational_unit', 'reviewed_at', 'admin_notes'] as $metric) {
                    $write(['Rejected research', $record, $metric === 'admin_notes' ? 'rejection_reason' : $metric, $submission->$metric]);
                }
                $write(['Rejected research', $record, 'researcher', $submission->researcher?->name]);
            }
            fclose($stream);
        }, 'research-reports-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
