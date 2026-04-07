@php
    $imageValue = old('image', $image ?? '');
    $previewId = 'image_preview_'.uniqid();
    $hiddenId = 'image_hidden_'.uniqid();
    $fileId = 'image_file_'.uniqid();
@endphp

<div class="grid grid-cols-4 gap-4 items-start pt-5">
    <label class="thunderbite-label" for="{{ $fileId }}">{{ $label }}</label>

    <div class="col-span-3 space-y-2">
        <img
            id="{{ $previewId }}"
            class="w-20 h-20 rounded object-cover border border-gray-200 {{ empty($imageValue) ? 'hidden' : '' }}"
            src="{{ $imageValue }}"
            alt="Prize image preview"
        />

        <input id="{{ $fileId }}" type="file" name="image_file" accept="image/*" class="thunderbite-input">
        {{-- <input id="{{ $hiddenId }}" type="hidden" name="image" value="{{ $imageValue }}"> --}}

        @error('image')
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
        @enderror

        @error('image_file')
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
        @enderror
    </div>
</div>

<script>
    (function () {
        const fileInput = document.getElementById('{{ $fileId }}');
        const hiddenInput = document.getElementById('{{ $hiddenId }}');
        const preview = document.getElementById('{{ $previewId }}');

        if (!fileInput || !hiddenInput || !preview) {
            return;
        }

        fileInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function (loadEvent) {
                const result = loadEvent.target && loadEvent.target.result;
                if (typeof result !== 'string') {
                    return;
                }

                hiddenInput.value = result;
                preview.src = result;
                preview.classList.remove('hidden');
            };

            reader.readAsDataURL(file);
        });
    })();
</script>


