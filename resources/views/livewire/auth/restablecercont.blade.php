<?php
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $username_or_email = '';
    public string $email_body = 'Hola, recibiste este correo porque solicitaste restablecer tu contraseña. Haz clic en el enlace para continuar.';
    public ?string $successMessage = null;
    public ?string $errorMessage = null;
    public ?string $sentEmail = null;

    public function processReset()
    {
        $this->reset(['successMessage', 'errorMessage', 'sentEmail']);

        $data = $this->validate([
            'username_or_email' => 'required|string',
            'email_body' => 'nullable|string',
        ], [
            'username_or_email.required' => 'Ingresa tu usuario o correo electrónico.',
        ]);

        $user = User::where('username', $data['username_or_email'])
            ->orWhere('email', $data['username_or_email'])
            ->first();

        if (! $user) {
            $this->errorMessage = 'No se encontró ningún usuario con ese nombre o correo.';
            return;
        }

        if (! $user->email) {
            $this->errorMessage = 'El usuario no tiene un correo registrado en el sistema.';
            return;
        }

        $token = hash_hmac('sha256', Str::random(64) . $user->email . now()->timestamp, config('app.key'));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );

        $url = route('restablecercont.reset', ['token' => $token]);

        try {
            Mail::to($user->email)->send(new PasswordResetMail(
                $user->name ?: $user->username,
                $url,
                $this->email_body,
            ));

            $this->successMessage = 'Correo de recuperación enviado correctamente.';
            $this->sentEmail = $user->email;
            $this->reset('username_or_email');
        } catch (\Exception $exception) {
            Log::error('Error al enviar correo de recuperación', ['exception' => $exception->getMessage()]);

            $this->errorMessage = config('app.debug')
                ? 'No se pudo enviar el correo: ' . $exception->getMessage()
                : 'No se pudo enviar el correo. Verifica la configuración de correo y vuelve a intentar.';
        }
    }
}; ?>

<div class="w-full max-w-2xl p-4">
    <x-card class="shadow-2xl border-t-4 border-primary bg-base-100">
        <div class="grid gap-8 lg:grid-cols-[320px_minmax(0,1fr)] items-center">
            <div class="rounded-3xl bg-primary/5 p-8 text-center">
                <x-icon name="o-key" class="mx-auto mb-4 w-16 h-16 text-primary" />
                <h1 class="text-3xl font-bold text-primary">Restablecer Contraseña</h1>
                <p class="mt-2 text-sm text-gray-500">Recibe un enlace en tu correo y crea una nueva contraseña segura.</p>
            </div>

            <div>
                @if ($errorMessage)
                    <x-alert title="Error" description="{{ $errorMessage }}" icon="o-exclamation-triangle" class="alert-error mb-4" />
                @endif

                @if ($successMessage)
                    <x-alert title="Correo enviado" description="Se envió el mensaje a {{ $sentEmail }}." icon="o-check-badge" class="alert-success mb-4" />
                @endif

                <x-form wire:submit="processReset">
                    <div class="grid gap-4">
                        <x-input
                            label="Usuario o correo"
                            wire:model="username_or_email"
                            icon="o-envelope"
                            placeholder="Ej. usuario o correo@dominio.com"
                            inline
                        />
                    </div>

                    

                    <x-slot:actions>
                        <x-button
                            label="Volver"
                            link="{{ route('login') }}"
                            wire:navigate
                            class="btn-ghost w-full sm:w-1/2"
                        />
                        <x-button
                            label="Enviar enlace"
                            type="submit"
                            icon="o-paper-airplane"
                            class="btn-primary w-full sm:w-1/2"
                            spinner="processReset"
                        />
                    </x-slot:actions>
                </x-form>
            </div>
        </div>
    </x-card>
</div>