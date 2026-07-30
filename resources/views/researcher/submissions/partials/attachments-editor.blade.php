@php
    $disabled = $disabled ?? false;
    $existingByType = ($existing ?? collect())->keyBy('document_type');
@endphp

<div class="grid gap-5">
    @foreach ($template->attachments as $attachment)
        @php $document = $existingByType->get($attachment->key); @endphp
        <div>
            <label class="text-sm font-medium text-slate-700">
                {{ $attachment->label }}
                @if ($attachment->required)
                    <span class="text-rose-500">*</span>
                @endif
            </label>
            @if ($document)
                <p class="mt-1 text-xs text-slate-500">Uploaded: {{ $document->original_name }}. Upload again to replace it.</p>
            @endif
            @unless ($disabled)
                <input type="file" name="attachments[{{ $attachment->key }}]" accept="application/pdf" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" />
            @endunless
        </div>
    @endforeach
</div>
