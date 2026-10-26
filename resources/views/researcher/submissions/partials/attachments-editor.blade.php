@php
    $disabled = $disabled ?? false;
    $existingByType = ($existing ?? collect())->groupBy('document_type');
    $editable = ! $disabled;
@endphp

<div class="grid gap-5">
    @php $approvalForms = $existingByType->get('approval_form') ?? collect(); @endphp
    <div>
        <span class="text-sm font-medium text-slate-700">Approval form from the School Research Management Committee</span>
        @if ($approvalForms->isNotEmpty())
            <ul class="mt-1 grid gap-1">
                @foreach ($approvalForms as $document)
                    <li class="flex items-center justify-between gap-2 text-xs text-slate-500">
                        <a href="{{ route('submissions.attachments.view', [$submission, $document]) }}" target="_blank" rel="noopener" class="truncate text-cherry-700 hover:underline">{{ $document->original_name }}</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-1 text-xs text-rose-600">Required approval form is missing.</p>
        @endif
        @unless ($disabled)
            <label class="mt-2 block text-xs text-slate-500">To replace it, upload a new PDF or photo.</label>
            <input type="file" name="approval_form" accept="application/pdf,image/*" class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
        @endunless
    </div>
    @foreach ($template->attachments as $attachment)
        @php $documents = $existingByType->get($attachment->key) ?? collect(); @endphp
        <div>
            <label class="text-sm font-medium text-slate-700">
                {{ $attachment->label }}
                @if ($attachment->required)
                    <span class="text-rose-500">*</span>
                @endif
            </label>
            @if ($documents->isNotEmpty())
                <ul class="mt-1 grid gap-1">
                    @foreach ($documents as $document)
                        <li class="flex items-center justify-between gap-2 text-xs text-slate-500">
                            <span class="truncate">{{ $document->original_name }}</span>
                            @unless ($disabled)
                                {{-- A <form> here (as this used to be) would nest inside the page's
                                     single outer draft form, which HTML forbids — the browser
                                     silently drops the inner <form> tag and closes the outer one
                                     early, pushing everything after it (later attachments, new
                                     file inputs, the Save button) outside the real form. --}}
                                <button type="button" data-attachment-remove data-attachment-url="{{ route('submissions.attachments.destroy', [$submission, $document]) }}" class="shrink-0 font-medium text-rose-600 hover:underline">Remove</button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif
            @unless ($disabled)
                <input type="file" name="attachments[{{ $attachment->key }}][]" accept="application/pdf" multiple class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
            @endunless
        </div>
    @endforeach
</div>
