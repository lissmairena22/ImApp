<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

new class extends Component {
    use Toast, WithFileUploads;

    public $backupFile;
    public bool $restoreModal = false;

    public function mount()
    {
        $dir = storage_path('app/backups');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
    }

    public function clearCache()
    {
        Artisan::call('optimize:clear');
        $this->success('Caché limpiada correctamente.');
    }

    public function backupDatabase()
    {
        $filename = "backup_imprenta_" . date('Y_m_d_H_i_s') . ".sql";
        $path = storage_path('app/backups/' . $filename);

        $dbUser = env('DB_USERNAME', 'root');
        $dbPass = env('DB_PASSWORD', '');
        $dbName = env('DB_DATABASE', 'imprenta_db');
        $host = env('DB_HOST', '127.0.0.1');

        $passwordString = $dbPass ? "-p\"{$dbPass}\"" : "";
        $command = "mysqldump -h {$host} -u {$dbUser} {$passwordString} {$dbName} > \"{$path}\"";

        try {
            exec($command);
            $this->success('Nuevo respaldo generado y guardado en el historial.');
        } catch (\Exception $e) {
            $this->error('Error al generar el respaldo local.');
        }
    }

    public function downloadBackup($filename)
    {
        $path = storage_path('app/backups/' . $filename);
        if (File::exists($path)) {
            return response()->download($path);
        }
        $this->error('El archivo ya no existe en el servidor.');
    }

    // NUEVO: Restaurar directamente seleccionando un ítem de la lista
    public function restoreFromList($filename)
    {
        $path = storage_path('app/backups/' . $filename);

        if (!File::exists($path)) {
            $this->error('El archivo seleccionado no existe.');
            return;
        }

        try {
            $dbUser = env('DB_USERNAME', 'root');
            $dbPass = env('DB_PASSWORD', '');
            $dbName = env('DB_DATABASE', 'imprenta_db');
            $host = env('DB_HOST', '127.0.0.1');

            $passwordString = $dbPass ? "-p\"{$dbPass}\"" : "";
            $command = "mysql -h {$host} -u {$dbUser} {$passwordString} {$dbName} < \"{$path}\"";

            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                $this->success("Sistema restaurado con éxito usando: {$filename}");
            } else {
                $this->error('Error al procesar el archivo SQL.');
            }
        } catch (\Exception $e) {
            $this->error('Ocurrió un error al intentar restaurar.');
        }
    }

    // NUEVO: Eliminar respaldos viejos para no saturar el disco duro
    public function deleteBackup($filename)
    {
        $path = storage_path('app/backups/' . $filename);
        if (File::exists($path)) {
            File::delete($path);
            $this->success('Respaldo eliminado del servidor.');
        }
    }

    // El archivo externo subido manualmente sigue funcionando aquí
    public function restoreDatabase()
    {
        $this->validate(['backupFile' => 'required|file']);
        try {
            $fullPath = $this->backupFile->getRealPath();
            $dbUser = env('DB_USERNAME', 'root');
            $dbPass = env('DB_PASSWORD', '');
            $dbName = env('DB_DATABASE', 'imprenta_db');
            $host = env('DB_HOST', '127.0.0.1');

            $passwordString = $dbPass ? "-p\"{$dbPass}\"" : "";
            $command = "mysql -h {$host} -u {$dbUser} {$passwordString} {$dbName} < \"{$fullPath}\"";

            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                $this->restoreModal = false;
                $this->reset('backupFile');
                $this->success('Base de datos externa restaurada con éxito.');
            } else {
                $this->error('Error con el archivo externo.');
            }
        } catch (\Exception $e) {
            $this->error('Error de restauración.');
        }
    }

    // MODIFICADO: Lee la carpeta local y lista los archivos dinámicamente
    public function with(): array
    {
        $backupDir = storage_path('app/backups');
        $backups = [];

        if (File::exists($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                if ($file->getExtension() === 'sql') {
                    $backups[] = [
                        'id' => $file->getFilename(), // MaryUI requiere un ID único
                        'filename' => $file->getFilename(),
                        'size' => round($file->getSize() / 1024, 2) . ' KB',
                        'date' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }

            // Ordenar los archivos para que el más nuevo aparezca arriba
            usort($backups, function($a, $b) {
                return strcmp($b['filename'], $a['filename']);
            });
        }

        return [
            'backupsList' => $backups,
            'headers' => [
                ['key' => 'date', 'label' => 'FECHA Y HORA DE CREACIÓN'],
                ['key' => 'filename', 'label' => 'NOMBRE DEL RESPALDO'],
                ['key' => 'size', 'label' => 'TAMAÑO'],
                ['key' => 'actions', 'label' => 'ACCIONES DISPONIBLES', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div>
    <x-header title="Mantenimiento del Sistema" subtitle="Gestión, optimización y respaldos de Imprenta Minerva" separator />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <x-card title="Crear Respaldo Local" icon="o-cloud-arrow-down" class="border-t-4 border-t-primary">
            <div class="text-sm text-gray-500 mb-4">
                Genera un punto de restauración actual y lo guarda directamente en el historial del sistema.
            </div>
            <x-slot:actions>
                <x-button label="Generar Punto" icon="o-plus" class="btn-primary w-full" wire:click="backupDatabase" spinner="backupDatabase" />
            </x-slot:actions>
        </x-card>

        <x-card title="Limpiar Caché" icon="o-trash" class="border-t-4 border-t-error">
            <div class="text-sm text-gray-500 mb-4">
                Libera espacio temporal y soluciona problemas visuales de carga en el servidor de inmediato.
            </div>
            <x-slot:actions>
                <x-button label="Limpiar Caché" icon="o-sparkles" class="btn-outline w-full text-error border-error hover:bg-error hover:border-error" wire:click="clearCache" spinner="clearCache" />
            </x-slot:actions>
        </x-card>

       
    </div>

    <x-card title="Historial de Puntos de Restauración" subtitle="Selecciona una versión anterior para regresar el tiempo del sistema" icon="o-clock">

        <x-slot:menu>
            <x-button label="Subir Respaldo Externo" icon="o-document-arrow-up" class="btn-sm btn-ghost" @click="$wire.restoreModal = true" />
        </x-slot:menu>

        <x-table :headers="$headers" :rows="$backupsList" striped>

            @scope('cell_filename', $backup)
                <span class="font-mono text-xs text-blue-600">{{ $backup['filename'] }}</span>
            @endscope

            @scope('cell_actions', $backup)
                <div class="flex gap-2">
                    <x-button icon="o-arrow-path" label="Restaurar aquí"
                        wire:click="restoreFromList('{{ $backup['filename'] }}')"
                        wire:confirm="⚠️ ¿Estás seguro de restaurar este punto? Se sobreescribirá la base de datos actual por completo."
                        spinner class="btn-xs btn-warning text-white font-bold" />

                    <x-button icon="o-arrow-down-tray"
                        wire:click="downloadBackup('{{ $backup['filename'] }}')"
                        class="btn-xs btn-circle btn-ghost text-primary" title="Descargar a la PC" />

                    <x-button icon="o-trash"
                        wire:click="deleteBackup('{{ $backup['filename'] }}')"
                        wire:confirm="¿Eliminar este archivo de respaldo del servidor?"
                        class="btn-xs btn-circle btn-ghost text-error" title="Borrar archivo" />
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center p-4 text-gray-400">
                    <x-icon name="o-archive-box-x-mark" class="w-8 h-8 inline mb-2" />
                    <p>No se han encontrado puntos de restauración guardados en el servidor.</p>
                </div>
            </x-slot:empty>

        </x-table>
    </x-card>

    <x-modal wire:model="restoreModal" title="Subir Respaldo Externo (.sql)" subtitle="Esta acción borrará los datos actuales" separator>
        <x-form wire:submit="restoreDatabase">
            <x-file wire:model="backupFile" label="Selecciona el archivo" accept=".sql" icon="o-document-text" />
            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.restoreModal = false" class="btn-ghost" />
                <x-button label="Subir y Restaurar" type="submit" icon="o-check" class="btn-warning text-white" spinner="restoreDatabase" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
