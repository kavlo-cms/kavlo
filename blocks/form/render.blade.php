@php
    $resolvedForm = \App\Services\EmbeddableFormRegistry::resolve($data['form_slug'] ?? '');
    $form = ($resolvedForm['type'] ?? null) === 'database' ? $resolvedForm['form'] : null;
@endphp

@if ($form)
    @php
        $blocks = \App\Services\FormBuilder::editorBlocks($form);
        $actionConfig = \App\Services\FormBuilder::resolvedActionConfig($form);
        $instanceId = (string) \Illuminate\Support\Str::uuid();
        $renderBlocks = function (array $items) use (&$renderBlocks, $form) {
            foreach ($items as $block) {
                $type = $block['type'] ?? '';
                $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
                $fieldKey = $blockData['key'] ?? '';

                if ($type === 'columns') {
                    $count = max(2, min(4, (int) ($blockData['count'] ?? 2)));
                    $gapClass = ['sm' => 'gap-2', 'md' => 'gap-6', 'lg' => 'gap-12'][$blockData['gap'] ?? 'md'] ?? 'gap-6';
                    @endphp
                    <div class="py-6 px-4">
                        <div class="flex {{ $gapClass }}">
                            @for ($i = 0; $i < $count; $i++)
                                <div class="min-w-0 flex-1 space-y-5">
                                    @php $renderBlocks(is_array($blockData['col_'.$i] ?? null) ? $blockData['col_'.$i] : []); @endphp
                                </div>
                            @endfor
                        </div>
                    </div>
                    @php
                    continue;
                }

                if ($type === 'button') {
                    @endphp
                    <button
                        type="submit"
                        class="cms-form-submit rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {{ $blockData['label'] ?? 'Submit' }}
                    </button>
                    @php
                    continue;
                }
                @endphp
                <div class="flex flex-col gap-1.5">
                    <label for="field-{{ $form->id }}-{{ $fieldKey }}" class="text-sm font-medium">
                        {{ $blockData['label'] ?? 'Field' }}
                        @if (!empty($blockData['required'])) <span class="text-red-500">*</span> @endif
                    </label>

                    @if ($type === 'textarea')
                        <textarea
                            id="field-{{ $form->id }}-{{ $fieldKey }}"
                            name="{{ $fieldKey }}"
                            placeholder="{{ $blockData['placeholder'] ?? '' }}"
                            @if(!empty($blockData['required'])) required @endif
                            rows="4"
                            class="cms-form-field rounded-md border bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        ></textarea>

                    @elseif ($type === 'select')
                        <select
                            id="field-{{ $form->id }}-{{ $fieldKey }}"
                            name="{{ $fieldKey }}"
                            @if(!empty($blockData['required'])) required @endif
                            class="cms-form-field rounded-md border bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">{{ $blockData['placeholder'] ?: '— Select —' }}</option>
                            @foreach (($blockData['options'] ?? []) as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>

                    @elseif (in_array($type, ['radio', 'checkbox']))
                        <div class="space-y-1.5">
                            @foreach (($blockData['options'] ?? []) as $opt)
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input
                                        type="{{ $type }}"
                                        name="{{ $type === 'checkbox' ? $fieldKey . '[]' : $fieldKey }}"
                                        value="{{ $opt['value'] }}"
                                        @if(!empty($blockData['required']) && $loop->first) required @endif
                                    />
                                    {{ $opt['label'] }}
                                </label>
                            @endforeach
                        </div>

                    @elseif (($blockData['input_type'] ?? 'text') === 'file')
                        <input
                            type="file"
                            id="field-{{ $form->id }}-{{ $fieldKey }}"
                            name="{{ $fieldKey }}"
                            @if(!empty($blockData['required'])) required @endif
                            class="text-sm"
                        />

                    @else
                        <input
                            type="{{ $blockData['input_type'] ?? 'text' }}"
                            id="field-{{ $form->id }}-{{ $fieldKey }}"
                            name="{{ $fieldKey }}"
                            placeholder="{{ $blockData['placeholder'] ?? '' }}"
                            @if(!empty($blockData['required'])) required @endif
                            class="cms-form-field rounded-md border bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    @endif

                    <span class="cms-form-error hidden text-xs text-red-500" data-field="{{ $fieldKey }}"></span>
                </div>
                @php
            }
        };
    @endphp
    <div class="py-8 px-4">
        <form
            id="form-{{ $form->id }}-{{ $instanceId }}"
            class="mx-auto max-w-xl space-y-5"
            data-form-id="{{ $form->id }}"
            data-form-slug="{{ $form->slug }}"
            data-success-message="{{ $actionConfig['success_message'] ?? 'Thank you for your submission!' }}"
            data-redirect-url="{{ $actionConfig['redirect_url'] ?? '' }}"
        >
            @csrf

            @php $renderBlocks($blocks); @endphp

            <div class="cms-form-alert hidden rounded-md border px-4 py-3 text-sm"></div>
        </form>
    </div>

    <script>
    (function () {
        var el = document.getElementById('form-{{ $form->id }}-{{ $instanceId }}');
        if (!el) return;

        el.addEventListener('submit', function (e) {
            e.preventDefault();

            var btn = el.querySelector('.cms-form-submit');
            var alert = el.querySelector('.cms-form-alert');
            var successMsg = el.dataset.successMessage;
            var redirectUrl = el.dataset.redirectUrl;

            // Clear errors
            el.querySelectorAll('.cms-form-error').forEach(function (n) { n.textContent = ''; n.classList.add('hidden'); });
            alert.classList.add('hidden');
            btn.disabled = true;
            btn.textContent = 'Sending…';

            var fd = new FormData(el);

            fetch('/forms/{{ $form->slug }}/submit', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '' },
                body: fd,
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        el.reset();
                        alert.textContent = successMsg;
                        alert.className = 'cms-form-alert rounded-md border border-green-500 bg-green-50 px-4 py-3 text-sm text-green-800';
                    }
                } else {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(function (key) {
                            var node = el.querySelector('.cms-form-error[data-field="' + key + '"]');
                            if (node) { node.textContent = data.errors[key][0]; node.classList.remove('hidden'); }
                        });
                    }
                    alert.textContent = data.message || 'Please fix the errors above.';
                    alert.className = 'cms-form-alert rounded-md border border-red-400 bg-red-50 px-4 py-3 text-sm text-red-800';
                    btn.disabled = false;
                    btn.textContent = 'Submit';
                }
            })
            .catch(function () {
                alert.textContent = 'Something went wrong. Please try again.';
                alert.className = 'cms-form-alert rounded-md border border-red-400 bg-red-50 px-4 py-3 text-sm text-red-800';
                btn.disabled = false;
                btn.textContent = 'Submit';
            });
        });
    })();
    </script>
@elseif ($resolvedForm)
    @php
        $registeredOutput = \App\Services\EmbeddableFormRegistry::renderRegistered($resolvedForm, is_array($data ?? null) ? $data : []);
    @endphp

    @if ($registeredOutput)
        {!! $registeredOutput !!}
    @else
        <div class="py-6 text-center text-sm text-muted-foreground">
            Form "{{ $resolvedForm['label'] ?? ($data['form_slug'] ?? '') }}" is registered but has no renderer.
        </div>
    @endif
@else
    <div class="py-6 text-center text-sm text-muted-foreground">
        Form "{{ $data['form_slug'] ?? '' }}" not found.
    </div>
@endif
