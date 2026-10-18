<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo e(__('ui.labels.title')); ?></title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; margin: 0; }
        .sheet { display: flex; flex-wrap: wrap; gap: 2mm; }
        .label { width: 52mm; height: 32mm; border: 0.2mm solid #d1d5db; border-radius: 1.5mm; padding: 2mm 2.5mm; overflow: hidden; page-break-inside: avoid; }
        .name { font-size: 8.5pt; font-weight: 600; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .meta { display: flex; justify-content: space-between; font-size: 7.5pt; color: #374151; margin-top: 0.5mm; }
        .price { font-weight: 700; }
        .barcode { margin-top: 1mm; text-align: center; }
        .barcode svg { max-width: 100%; height: 11mm; }
        .code { font-size: 7.5pt; letter-spacing: 0.12em; text-align: center; color: #111827; }
        @media print { .label { border-color: transparent; } }
    </style>
</head>
<body onload="window.print()">
    <div class="sheet">
        <?php $__currentLoopData = $labels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="label">
                <div class="name"><?php echo e($label['name']); ?></div>
                <div class="meta"><span><?php echo e($label['sku']); ?></span><span class="price"><?php echo e(\App\Support\Money::fromDecimal((string) $label['price'])->format()); ?></span></div>
                <div class="barcode"><?php echo $label['svg']; ?></div>
                <div class="code"><?php echo e($label['barcode_text']); ?></div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</body>
</html>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/inventory/labels/print.blade.php ENDPATH**/ ?>