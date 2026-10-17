<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

        <title><?php echo e(config('app.name', 'Lentera')); ?></title>

        <!-- Fonts: Plus Jakarta Sans (token tipografi F.2.0) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen">
            
            <header class="bg-white shadow-soft">
                <?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <?php if(isset($header)): ?>
                    <div class="border-t border-gray-100">
                        <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                            <?php echo e($header); ?>

                        </div>
                    </div>
                <?php endif; ?>
            </header>

            <!-- Page Content -->
            <main>
                <?php echo e($slot); ?>

            </main>
        </div>
    </body>
</html>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/layouts/app.blade.php ENDPATH**/ ?>