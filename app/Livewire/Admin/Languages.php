<?php

namespace App\Livewire\Admin;

use App\Enums\LanguageKind;
use App\Models\Language;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Admin → Languages: which languages and servers are synced, and from which OSV ecosystems and NVD CPEs. */
#[Layout('layouts.app')]
#[Title('Languages')]
class Languages extends Component
{
    public ?int $editingId = null;

    public bool $editing = false;

    /** @var array{slug: string, name: string, kind: string, osv_ecosystems: string, nvd_cpes: string} */
    public array $form = ['slug' => '', 'name' => '', 'kind' => 'language', 'osv_ecosystems' => '', 'nvd_cpes' => ''];

    public function toggle(int $id): void
    {
        $language = Language::query()->findOrFail($id);
        $language->update(['enabled' => ! $language->enabled]);
    }

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->editing = true;
        $this->form = ['slug' => '', 'name' => '', 'kind' => 'language', 'osv_ecosystems' => '', 'nvd_cpes' => ''];
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $language = Language::query()->findOrFail($id);
        $this->editingId = $language->id;
        $this->editing = true;
        $this->form = [
            'slug' => $language->slug,
            'name' => $language->name,
            'kind' => $language->kind->value,
            'osv_ecosystems' => implode(', ', $language->osv_ecosystems ?? []),
            'nvd_cpes' => implode(', ', $language->nvd_cpes ?? []),
        ];
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->editingId = null;
    }

    public function save(): void
    {
        $this->validate([
            'form.slug' => ['required', 'alpha_dash', 'max:50', Rule::unique('languages', 'slug')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.kind' => ['required', Rule::enum(LanguageKind::class)],
            'form.osv_ecosystems' => ['nullable', 'string', 'max:1000'],
            'form.nvd_cpes' => ['nullable', 'string', 'max:2000', function ($attribute, $value, $fail) {
                foreach (self::split((string) $value) as $cpe) {
                    if (! preg_match('/^cpe:2\.3:[aho]:[^:]+:[^:]+/', $cpe)) {
                        $fail("\"$cpe\" is not a CPE 2.3 name like cpe:2.3:a:vendor:product.");
                    }
                }
            }],
        ]);

        $attributes = [
            'slug' => $this->form['slug'],
            'name' => $this->form['name'],
            'kind' => $this->form['kind'],
            'osv_ecosystems' => self::split($this->form['osv_ecosystems']),
            'nvd_cpes' => self::split($this->form['nvd_cpes']),
        ];

        $this->editingId
            ? Language::query()->findOrFail($this->editingId)->update($attributes)
            : Language::query()->create($attributes + ['enabled' => true]);

        $this->editing = false;
        $this->editingId = null;
        session()->flash('status', 'Saved '.$attributes['name'].'.');
    }

    /** @return list<string> */
    private static function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }

    public function render()
    {
        return view('livewire.admin.languages', [
            'languages' => Language::query()->withCount('issues')->orderBy('kind')->orderBy('name')->get(),
            'kinds' => LanguageKind::cases(),
        ]);
    }
}
