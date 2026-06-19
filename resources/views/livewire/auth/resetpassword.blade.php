<?php
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $token = '';
    public ?string $email = null;
    public string $password = '';
    public string $password_confirmation = '';
    public ?string $statusMessage = null;
    public ?string $errorMessage = null;
    public bool $invalidToken = false;

    public function mount(): void
    {
        $this->token = $this->token ?: request()->route('token') ?: request()->query('token');

        if (! $this->token) {
            $this->invalidToken = true;
            $this->errorMessage = 'Token no proporcionado.';
            return;
        }

        $record = DB::table('password_reset_tokens')->where('token', $this->token)->first();

        if (! $record) {
            $this->invalidToken = true;
            $this->errorMessage = 'Token inválido o ya no existe.';
            return;
        }

        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $createdAt = Carbon::parse($record->created_at);

        if ($createdAt->lt(now()->subMinutes($expireMinutes))) {
            $this->invalidToken = true;
            $this->errorMessage = 'El token de restablecimiento ha expirado.';
            return;
        }

        $this->email = $record->email;
    }

    public function resetPassword()
    {
        if ($this->invalidToken) {
            $this->errorMessage = 'Token inválido o expirado. Solicita un nuevo restablecimiento.';
            return;
        }

        $data = $this->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $record = DB::table('password_reset_tokens')->where('token', $this->token)->first();

        if (! $record) {
            $this->invalidToken = true;
            $this->errorMessage = 'Token inválido o ya no existe.';
            return;
        }

        $user = User::where('email', $record->email)->first();

        if (! $user) {
            $this->invalidToken = true;
            $this->errorMessage = 'No se encontró el usuario asociado al token.';
            return;
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $record->email)->delete();

        $this->statusMessage = 'Contraseña actualizada correctamente. Redirigiendo al login...';
        redirect()->route('login');
    }
}; ?>

<div class="w-full max-w-sm p-4">
    <x-card class="shadow-2xl border-t-4 border-primary bg-base-100">
        <div class="mb-8 text-center">
            <x-icon name="o-key" class="w-12 h-12 text-primary mb-2" />
            <h1 class="text-2xl font-bold italic">Restablecer Contraseña</h1>
            <p class="text-sm text-gray-500">Ingresa tu nueva contraseña para terminar el restablecimiento.</p>
        </div>

        @if ($invalidToken)
            <x-alert title="Error" description="{{ $errorMessage }}" icon="o-exclamation-triangle" class="alert-error mb-4" />
            <div class="text-center">
                <x-button label="Solicitar nuevo enlace" link="{{ route('restablecercont') }}" wire:navigate class="btn-primary" />
            </div>
        @else
            <x-form wire:submit="resetPassword">
                <x-input
                    label="Email asociado"
                    value="{{ $email }}"
                    icon="o-envelope"
                    readonly
                    inline
                />

                <x-input
                    label="Nueva contraseña"
                    wire:model="password"
                    type="password"
                    icon="o-key"
                    placeholder="********"
                    inline
                />

                <x-input
                    label="Confirmar contraseña"
                    wire:model="password_confirmation"
                    type="password"
                    icon="o-key"
                    placeholder="********"
                    inline
                />

                @if ($errorMessage)
                    <p class="text-error text-sm mb-3">{{ $errorMessage }}</p>
                @endif

                <x-slot:actions>
                    <x-button
                        label="Volver"
                        link="{{ route('login') }}"
                        wire:navigate
                        class="btn-ghost w-1/3"
                    />
                    <x-button
                        label="Actualizar contraseña"
                        type="submit"
                        icon="o-check"
                        class="btn-primary w-2/3"
                        spinner="resetPassword"
                    />
                </x-slot:actions>
            </x-form>
        @endif
    </x-card>
</div>
