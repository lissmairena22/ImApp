<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new
 #[Layout('layouts.guest')]

class extends Component {
    public string $username = '';
    public string $password = '';

    public function authenticate()
    {
        $credentials = $this->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            session()->regenerate();
            return redirect()->intended('/');
        }

        $this->addError('username', 'Usuario o contraseña incorrectos.');
    }
}; ?>

<div class="w-full max-w-sm p-4">
    <x-card class="shadow-2xl border-t-4 border-primary bg-base-100">
        <div class="mb-8 text-center">
            <x-icon name="o-printer" class="w-12 h-12 text-primary mb-2" />
            <h1 class="text-2xl font-bold italic">Imprenta América</h1>
            <p class="text-sm text-gray-500">Gestión de Producción y Ventas</p>
        </div>

        <x-form wire:submit="authenticate">
            <x-input
                label="Usuario"
                wire:model="username"
                icon="o-user"
                placeholder="Ingresa tu usuario"
                inline
            />

            <x-input
                label="Contraseña"
                wire:model="password"
                type="password"
                icon="o-key"
                placeholder="********"
                inline
            />

            <x-slot:actions>
                <x-button
                    label="Iniciar Sesión"
                    type="submit"
                    icon="o-arrow-right-on-rectangle"
                    class="btn-primary w-full"
                    spinner="authenticate"
                />
            </x-slot:actions>
        </x-form>

        <div class="mt-4 text-center">
            <span class="text-xs text-gray-400">v1.0.2 - Sistemas 2026</span>
        </div>
    </x-card>
</div>
