<?php

namespace App\Modules\Lab\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Kelola template titik QC (F2.1, Blueprint B.6 + PRD Detail 5.2).
 *
 * Template adalah konfigurasi: hanya Owner/Admin yang boleh mengelola
 * (Blueprint: "dapat dikelola Admin") — ditegakkan di service agar
 * semua jalur (UI maupun pemanggil lain) sama-sama tertolak. Tanpa
 * hapus: titik dinonaktifkan lewat is_active (aturan tanpa-hapus).
 */
class QcTemplateService
{
    public function __construct(private readonly AuditLogger $audit) {}

    private function assertManager(User $actor): void
    {
        if (! $actor->hasRole('Owner') && ! $actor->hasRole('Admin')) {
            throw new AuthorizationException(__('ui.qc.manager_only'));
        }
    }

    public function create(User $actor, string $name, bool $isRequired, ?int $sortOrder = null): QcPointTemplate
    {
        $this->assertManager($actor);

        $template = QcPointTemplate::create([
            'name' => $name,
            'is_required' => $isRequired,
            'sort_order' => $sortOrder ?? ((int) QcPointTemplate::max('sort_order') + 1),
            'is_active' => true,
        ]);

        $this->audit->log(
            action: 'qc_template.create',
            subjectType: 'qc_point_template',
            subjectId: $template->id,
            newValues: ['name' => $template->name, 'is_required' => $template->is_required, 'sort_order' => $template->sort_order],
            actor: $actor,
        );

        return $template;
    }

    public function update(User $actor, QcPointTemplate $template, string $name, bool $isRequired, int $sortOrder): QcPointTemplate
    {
        $this->assertManager($actor);

        $old = ['name' => $template->name, 'is_required' => $template->is_required, 'sort_order' => $template->sort_order];
        $template->update(['name' => $name, 'is_required' => $isRequired, 'sort_order' => $sortOrder]);

        $this->audit->log(
            action: 'qc_template.update',
            subjectType: 'qc_point_template',
            subjectId: $template->id,
            oldValues: $old,
            newValues: ['name' => $template->name, 'is_required' => $template->is_required, 'sort_order' => $template->sort_order],
            actor: $actor,
        );

        return $template;
    }

    public function setActive(User $actor, QcPointTemplate $template, bool $active): QcPointTemplate
    {
        $this->assertManager($actor);

        $template->update(['is_active' => $active]);
        $this->audit->log(
            action: $active ? 'qc_template.activate' : 'qc_template.deactivate',
            subjectType: 'qc_point_template',
            subjectId: $template->id,
            newValues: ['is_active' => $active],
            actor: $actor,
        );

        return $template;
    }
}
