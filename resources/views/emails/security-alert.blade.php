<x-mail::message>
    # {{ $alertInfo['subject'] }}

    Шановний(а) {{ $user->name }},

    {{ $alertInfo['message'] }}

    ## Деталі події:

    @if(isset($details['action']))
        - **Дія**: {{ $details['action'] }}
    @endif

    @if(isset($details['location']))
        - **Місцезнаходження**: {{ $details['location'] }}
    @elseif(isset($ip))
        - **IP-адреса**: {{ $ip }}
    @endif

    @if(isset($details['device']))
        - **Пристрій**: {{ $details['device'] }}
    @elseif(isset($userAgent))
        - **Пристрій**: {{ $userAgent }}
    @endif

    - **Час**: {{ $time }}

    @if($alertInfo['critical'])
        <x-mail::panel>
            **Увага!** Ця подія вимагає вашої негайної уваги. Якщо ви не ініціювали цю дію, негайно виконайте такі кроки:
            1. Увійдіть до свого облікового запису та змініть пароль
            2. Перевірте налаштування безпеки вашого профілю
            3. Зв'яжіться зі службою підтримки
        </x-mail::panel>
    @endif

    @if(isset($details['next_steps']))
        ## Наступні кроки:

        @foreach($details['next_steps'] as $step)
            - {{ $step }}
        @endforeach
    @endif

    <x-mail::button :url="route('login')">
        Перейти до облікового запису
    </x-mail::button>

    Якщо у вас виникли запитання або занепокоєння, зверніться до нашої служби підтримки.

    З повагою,<br>
    {{ config('app.name') }} Команда безпеки
</x-mail::message>
