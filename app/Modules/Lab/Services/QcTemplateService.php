<?php

namespace App\Modules\Lab\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Kelola template titik QC (F2.1, Blueprint B.6 + PRD Detail 5.2).
 *
 * Template adalah konfigurasi BERSAMA se-instalasi: sejak audit
 * isolasi menyeluruh 18 Nov 2026 (ISO-3) pengelolaannya hanya untuk
 * aktor platform dan instalasi mandiri — aktor tenant tidak pernah
 * boleh mengubah daftar periksa yang dipakai tenant lain. Pada
 * instalasi mandiri, pengelola tetap Owner/Admin. Ditegakkan di
 * service agar semua jalur (UI maupun pemanggil lain) sama-sama
 * tertolak, sejajar gerbang rute qc.template-admin. Tanpa hapus:
 * titik dinonaktifkan lewat is_active (aturan tanpa-hapus).
 */
class QcTemplateService
{
    public function __construct(private readonly AuditLogger $audit) {}

    private function assertManager(User $actor): void
    {
        $mode = TenantContext::for($actor)->mode();

        if ($mode === TenantContext::MODE_PLATFORM) {
            return;
        }

        if ($mode !== TenantContext::MODE_STANDALONE
            || (! $actor->hasRole('Owner') && ! $actor->hasRole('Admin'))) {
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
