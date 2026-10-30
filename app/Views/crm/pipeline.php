<?php
$columnLink = static function (string $key, int $number) use ($selectedProject, $selectedStatus, $searchQuery, $columnPages, $dueFilter): string {
    $columnPages[$key] = $number;
    return base_url('crm?' . http_build_query(['project_id' => $selectedProject, 'view' => 'pipeline', 'status' => $selectedStatus, 'search' => $searchQuery, 'due'=>$dueFilter, 'column_page' => $columnPages])) . '#pipeline-' . $key;
};
?>
<div class="crm-pipeline-intro">
    <p>Ubah tahap melalui pilihan pada kartu. Di desktop, geser papan untuk melihat tahap lain atau seret pegangan di pojok kartu untuk memindahkannya.</p>
    <label class="crm-pipeline-mobile-stage">Tahap yang ditampilkan
        <select id="crmPipelineStage"><?php foreach ($pipeline as $key => $column): ?><option value="<?= e($key) ?>"><?= e($stages[$key]['label']) ?> (<?= $column['total'] ?>)</option><?php endforeach; ?></select>
    </label>
</div>
<div id="crmPipeline" class="crm-pipeline custom-scrollbar" tabindex="0" role="region" aria-label="Pipeline penjualan, geser untuk melihat seluruh tahap">
    <?php foreach ($pipeline as $key => $column): ?>
    <section id="pipeline-<?= e($key) ?>" class="crm-pipeline-column" data-pipeline-stage="<?= e($key) ?>" aria-label="<?= e($stages[$key]['label']) ?>">
        <header class="crm-pipeline-heading"><h2><?= e($stages[$key]['label']) ?></h2><span aria-label="Jumlah prospek"><?= number_format($column['total']) ?></span></header>
        <div class="crm-pipeline-cards">
        <?php foreach ($column['leads'] as $lead):
            $id = (int)$lead['id'];
            $detailUrl = base_url('crm/detail?' . http_build_query(['id' => $id, 'view' => 'pipeline', 'status' => $selectedStatus, 'due'=>$dueFilter, 'search' => $searchQuery]));
            $due = $lead['next_followup'] ? strtotime($lead['next_followup']) : null;
        ?>
            <article class="crm-pipeline-card" data-pipeline-card="<?= $id ?>" data-stage="<?= e($key) ?>">
                <span class="crm-pipeline-drag" draggable="true" data-pipeline-drag="<?= $id ?>" aria-hidden="true" title="Seret ke tahap lain">⠿</span>
                <a class="crm-business" data-detail href="<?= e($detailUrl) ?>"><?= e($lead['shop_name']) ?></a>
                <?php if ((float)$lead['deal_value'] > 0): ?><p class="crm-pipeline-value">Rp <?= number_format((float)$lead['deal_value'], 0, ',', '.') ?></p><?php endif; ?>
                <p class="crm-pipeline-due <?= $due && $due < time() ? 'is-overdue' : '' ?>"><?php if ($due): ?><?= $due < time() ? 'Terlewat: ' : 'Follow-up: ' ?><time datetime="<?= e(date('c', $due)) ?>"><?= e(date('d/m/Y H:i', $due)) ?></time><?php else: ?>Belum ada jadwal follow-up<?php endif; ?></p>
                <label class="crm-pipeline-status-label">Tahap
                    <select class="crm-status" data-stage="<?= e($key) ?>" data-status="<?= $id ?>" data-original="<?= e($key) ?>" aria-label="Status <?= e($lead['shop_name']) ?>"><?php foreach ($stages as $stageKey => $stage): ?><option value="<?= e($stageKey) ?>" <?= $key === $stageKey ? 'selected' : '' ?>><?= e($stage['label']) ?></option><?php endforeach; ?></select>
                </label>
            </article>
        <?php endforeach; ?>
        <?php if (!$column['leads']): ?><p class="crm-pipeline-empty"><?= $hasFilters ? 'Tidak ada prospek yang cocok pada tahap ini.' : 'Belum ada prospek pada tahap ini.' ?></p><?php endif; ?>
        </div>
        <?php if ($column['total'] > 20): ?>
        <footer class="crm-pipeline-pagination"><small>Halaman <?= $column['page'] ?> / <?= (int)ceil($column['total'] / 20) ?></small><nav aria-label="Halaman <?= e($stages[$key]['label']) ?>"><?php if ($column['page'] > 1): ?><a class="crm-secondary" href="<?= e($columnLink($key, $column['page'] - 1)) ?>">Sebelumnya</a><?php endif; ?><?php if ($column['hasNext']): ?><a class="crm-secondary" href="<?= e($columnLink($key, $column['page'] + 1)) ?>">Berikutnya</a><?php endif; ?></nav></footer>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
</div>
