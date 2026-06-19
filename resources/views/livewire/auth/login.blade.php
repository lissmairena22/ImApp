<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.guest')] class extends Component {
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

<div class="flex flex-col items-center justify-center min-h-screen p-6 bg-base-200">
    
    <x-card class="w-full max-w-md shadow-xl border border-base-300 overflow-hidden">
        
        <!-- Header con un toque elegante -->
        <div class="p-6 border-b border-base-200 text-center bg-base-100">
            <div class="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-4">
                <x-icon name="o-printer" class="w-8 h-8 text-primary" />
            </div>
            <h1 class="text-3xl font-extrabold text-base-content tracking-tight">Imprenta Minnerva</h1>
            <p class="text-sm text-base-content/60 font-medium">Gestión de Producción y Ventas</p>
        </div>

        <div class="p-6">
            <x-form wire:submit="authenticate">
                <x-input
                    label="Usuario"
                    wire:model="username"
                    icon="o-user"
                    placeholder="Tu usuario"
                    class="input-bordered"
                />

                <x-input
                    label="Contraseña"
                    wire:model="password"
                    type="password"
                    icon="o-key"
                    placeholder="••••••••"
                    class="input-bordered"
                />

                <x-slot:actions>
                    <div class="w-full flex flex-col gap-3">
                        <x-button
                            label="Iniciar Sesión"
                            type="submit"
                            icon="o-arrow-right-on-rectangle"
                            class="btn-primary w-full shadow-lg shadow-primary/20"
                            spinner="authenticate"
                        />
                        
                        <a href="{{ route('restablecercont') }}" wire:navigate 
                           class="text-xs text-center text-primary hover:underline transition-all">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                </x-slot:actions>
            </x-form>
        </div>

        <!-- Footer discreto -->
        <div class="p-4 bg-base-200/50 text-center">
            <span class="text-[10px] uppercase tracking-widest text-base-content/40 font-semibold">
                v1.0.2 - Sistemas 2026
            </span>
        </div>
    </x-card>
</div>