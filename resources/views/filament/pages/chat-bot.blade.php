<x-filament-panels::page class="p-0">
    <div class="p-6 space-y-4">
        <p class="text-gray-600 dark:text-gray-300 text-lg leading-relaxed">
            Esta IA te ayudará a tomar mejores decisiones sobre tus productos, precios y consultas basadas en los datos
            disponibles de tu plataforma.
            <span class="font-semibold text-emerald-600 dark:text-emerald-400">Explora, consulta y actúa con
                inteligencia.</span>
        </p>
    </div>
    <div class="p-4 space-y-2">
        <p class="font-semibold text-gray-700 dark:text-gray-200">Respuestas rápidas:</p>
        <div class="flex flex-wrap gap-2">
            @php
                $botones = [
                    'vendieron' => 'Más vendidos',
                    'movimiento' => 'Movimientos',
                    'compras' => 'Compras',
                    'recomienda' => 'Recomendar',
                    'bajo stock' => 'Bajo stock',
                    'inventario' => 'Inventario',
                    'producto' => 'Productos',
                    'proveedor' => 'Proveedores',
                    'pronóstico' => 'Pronóstico ventas',
                    'promociones' => 'Promociones',
                    'márgenes' => 'Márgenes',
                    'rotación' => 'Rotación',
                ];
            @endphp

            @foreach ($botones as $mensaje => $label)
                <button onclick="enviarRespuestaRapida('{{ $mensaje }}')"
                    class="px-4 py-2 rounded-full text-sm font-semibold shadow-sm ring-1 ring-emerald-400 bg-emerald-100 text-gray-800 hover:bg-emerald-200 dark:bg-emerald-600 dark:text-white dark:hover:bg-emerald-700 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 dark:focus:ring-offset-gray-900">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>
    <div
        class="flex flex-col w-full h-screen border rounded-none overflow-hidden
                bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
        <div id="chat-history"
            class="flex-1 p-4 overflow-y-auto space-y-4
                                     scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-700 scrollbar-thumb-rounded">
        </div>
        <form id="chat-form"
            class="flex border-t border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4 gap-3"
            onsubmit="event.preventDefault(); enviarMensaje();">
            <textarea id="mensaje" rows="2" placeholder="Escribe tu mensaje..."
                class="flex-1 resize-none rounded-xl border border-gray-300 bg-white px-4 py-2
                       text-gray-700 placeholder-gray-400
                       focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent
                       dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:placeholder-gray-500
                       transition-all duration-200"
                required></textarea>

            <button type="submit"
                class="inline-flex items-center justify-center rounded-xl bg-white dark:bg-gray-700 px-6 py-2
           text-gray-600 dark:text-white shadow-lg border border-gray-300 dark:border-gray-600
           hover:bg-gray-100 dark:hover:bg-gray-600
           focus:outline-none focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-700
           transition-all duration-200 font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Enviar
            </button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        const mensajes = [];
        const chatHistory = document.getElementById('chat-history');
        const textarea = document.getElementById('mensaje');

        function enviarRespuestaRapida(mensajeRapido) {
            textarea.value = mensajeRapido;
            enviarMensaje();
        }

        function renderMensajes() {
            chatHistory.innerHTML = '';
            mensajes.forEach(({
                sender,
                text
            }) => {
                const div = document.createElement('div');
                div.classList.add(
                    'max-w-[75%]',
                    'p-3',
                    'rounded-2xl',
                    'whitespace-pre-wrap',
                    'break-words',
                    'shadow',
                    'transition-all',
                    'duration-300'
                );
                if (sender === 'user') {
                    div.classList.add(
                        'ml-auto',
                        'bg-emerald-100',
                        'text-emerald-900',
                        'dark:bg-emerald-900',
                        'dark:text-emerald-300'
                    );
                } else {
                    div.classList.add(
                        'mr-auto',
                        'bg-gray-200',
                        'text-gray-800',
                        'dark:bg-gray-700',
                        'dark:text-gray-300'
                    );
                }
                div.innerHTML = marked.parse(text);

                chatHistory.appendChild(div);
            });
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }

        async function enviarMensaje() {
            const texto = textarea.value.trim();
            if (!texto) return;
            mensajes.push({
                sender: 'user',
                text: texto
            });
            renderMensajes();
            textarea.value = '';
            textarea.focus();
            mensajes.push({
                sender: 'bot',
                text: 'Consultando a Ark Intelligence...'
            });
            renderMensajes();

            try {
                const response = await fetch('/api/chat-gemini', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        mensaje: texto
                    })
                });
                if (!response.ok) throw new Error('Error en la respuesta de Ark Intelligence.');
                const data = await response.json();
                mensajes.pop();
                mensajes.push({
                    sender: 'bot',
                    text: data.respuesta || 'No se obtuvo respuesta.'
                });
                renderMensajes();
            } catch (error) {
                mensajes.pop();
                mensajes.push({
                    sender: 'bot',
                    text: 'Error al consultar la Ark Intelligence: ' + error.message
                });
                renderMensajes();
            }
        }

        textarea.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                enviarMensaje();
            }
        });
    </script>

    <style>
        #chat-history::-webkit-scrollbar {
            width: 8px;
        }

        #chat-history::-webkit-scrollbar-track {
            background: transparent;
        }

        #chat-history::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 9999px;
        }

        html.dark #chat-history::-webkit-scrollbar-thumb {
            background-color: #4b5563;
        }
    </style>
</x-filament-panels::page>
