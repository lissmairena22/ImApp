<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Artisan;

new class extends Component {
    use Toast, WithFileUploads;

     // para guardar el archivo temporalmente
    public $backupFile;
    
     // Controla la ventanita modal
    public bool $restoreModal = false;

    public function clearCache()
    {
        Artisan::call('optimize:clear');
        $this->success('Caché limpiada correctamente.');
    }

    public function optimize()
    {
        Artisan::call('optimize');
        $this->success('Sistema optimizado para mayor velocidad.');
    }

    public function backupDatabase()
    {
        $filename = "backup_imprenta_" . date('Y_m_d_H_i_s') . ".sql";
        $path = storage_path('app/public/' . $filename);

        $dbUser = env('DB_USERNAME', 'root');
        $dbPass = env('DB_PASSWORD', '');
        $dbName = env('DB_DATABASE', 'imprenta_db');
        $host = env('DB_HOST', '127.0.0.1');

        $passwordString = $dbPass ? "-p\"{$dbPass}\"" : "";
        $command = "mysqldump -h {$host} -u {$dbUser} {$passwordString} {$dbName} > \"{$path}\"";

        try {
            exec($command);
            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            $this->error('Error al generar el respaldo.');
        }
    }

    public function restoreDatabase()
    {
        $this->validate([
            'backupFile' => 'required|file',
        ]);

        try {
            $fullPath = $this->backupFile->getRealPath();

            $dbUser = env('DB_USERNAME', 'root');
            $dbPass = env('DB_PASSWORD', '');
            $dbName = env('DB_DATABASE', 'imprenta_db');
            $host = env('DB_HOST', '127.0.0.1');

            $passwordString = $dbPass ? "-p\"{$dbPass}\"" : "";
            $command = "mysql -h {$host} -u {$dbUser} {$passwordString} {$dbName} < \"{$fullPath}\"";

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $this->error('Error de sintaxis o de conexión al restaurar.');
                return;
            }

            $this->restoreModal = false;
            $this->reset('backupFile');
            $this->success('Base de datos restaurada con éxito.');

        } catch (\Exception $e) {
            $this->error('Error al intentar restaurar el sistema.');
        }
    }
}; ?>

<div>
    <x-header title="Mantenimiento del Sistema" subtitle="Gestión, optimización y respaldos de Imprenta Minerva" separator />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <x-card title="Respaldar BD" icon="o-cloud-arrow-down" class="border-t-4 border-t-primary">
            <div class="text-sm text-gray-500 mb-6">
                Genera y descarga una copia segura de todos los datos actuales.
            </div>
            <x-slot:actions>
                <x-button label="Descargar" icon="o-arrow-down-tray" class="btn-primary w-full" wire:click="backupDatabase" spinner="backupDatabase" />
            </x-slot:actions>
        </x-card>

        <x-card title="Restaurar BD" icon="o-cloud-arrow-up" class="border-t-4 border-t-warning">
            <div class="text-sm text-gray-500 mb-6">
                Sube un archivo .sql para volver a un estado anterior del sistema.
            </div>
            <x-slot:actions>
                <x-button label="Restaurar" icon="o-arrow-path" class="btn-warning w-full text-white" @click="$wire.restoreModal = true" />
            </x-slot:actions>
        </x-card>

        <x-card title="Limpiar Caché" icon="o-trash" class="border-t-4 border-t-error">
            <div class="text-sm text-gray-500 mb-6">
                Libera espacio y borra memoria temporal del servidor.
            </div>
            <x-slot:actions>
                <x-button label="Limpiar" icon="o-sparkles" class="btn-outline w-full text-error border-error hover:bg-error hover:border-error" wire:click="clearCache" spinner="clearCache" />
            </x-slot:actions>
        </x-card>

        <x-card title="Optimizar" icon="o-bolt" class="border-t-4 border-t-success">
            <div class="text-sm text-gray-500 mb-6">
                Mejora la velocidad de carga empaquetando configuraciones.
            </div>
            <x-slot:actions>
                <x-button label="Ejecutar" icon="o-rocket-launch" class="btn-outline w-full text-success border-success hover:bg-success hover:border-success" wire:click="optimize" spinner="optimize" />
            </x-slot:actions>
        </x-card>

    </div>

    <x-modal wire:model="restoreModal" title="⚠️ ¡Atención! Restaurar Sistema" subtitle="Esta acción sobreescribirá toda tu base de datos actual." separator>

        <div class="mb-4 text-sm text-gray-600">
            Asegúrate de seleccionar el archivo <strong>.sql</strong> correcto. Toda la información ingresada después de la fecha de este respaldo se perderá permanentemente.
        </div>

        <x-form wire:submit="restoreDatabase">
            <x-file wire:model="backupFile" label="Selecciona tu archivo de respaldo (.sql)" accept=".sql" icon="o-document-text" />

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.restoreModal = false" class="btn-ghost" />
                <x-button label="Subir y Restaurar" type="submit" icon="o-check" class="btn-warning text-white" spinner="restoreDatabase" />
            </x-slot:actions>
        </x-form>
    </x-modal>

</div>
