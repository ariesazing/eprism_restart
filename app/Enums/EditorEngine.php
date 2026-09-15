<?php

namespace App\Enums;

/**
 * Which editing model a submission's chapters use — assigned once at creation
 * (ResearchSubmissionController::store()) and frozen thereafter, so an existing draft never
 * silently changes editors underneath a researcher mid-way through writing it.
 *
 * - CANVAS_EDITOR: per-chapter HTML, edited in-browser (canvas-editor). Used when ONLYOFFICE
 *   isn't configured/enabled.
 * - ONLYOFFICE: per-chapter drafting — the current default for new submissions once
 *   ONLYOFFICE is enabled. Each rich_text chapter is its own isolated .docx the researcher
 *   edits in ONLYOFFICE (chapter-panels.blade.php); table-type sections stay plain HTML
 *   inputs regardless of engine. SubmissionDocxComposer/SubmissionDocxPdfMerger insert each
 *   chapter's own converted PDF into the admin's template at generation time.
 * - ONLYOFFICE_MANUSCRIPT: the whole manuscript (front matter + every chapter) as one
 *   continuously-edited .docx — no longer assigned to new submissions, kept only so
 *   submissions already using it keep working (see ManuscriptService).
 */
enum EditorEngine: string
{
    case CANVAS_EDITOR = 'canvas_editor';
    case ONLYOFFICE = 'onlyoffice';
    case ONLYOFFICE_MANUSCRIPT = 'onlyoffice_manuscript';
}
