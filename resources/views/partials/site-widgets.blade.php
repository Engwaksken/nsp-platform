{{--
    Floating helpers on every page (public, member and admin layouts):
    - the accessibility panel (universal-access button; Alt+Shift+A): text size and spacing, readable font, colour and
      contrast modes, link/heading highlighting, focus outline, big cursor, reading guide or mask, stop animations,
      hide images, read aloud, and one-click profiles. Settings are saved on this device (js/theme.js applies them
      before the page is drawn; js/widgets.js runs the panel; css/widgets.css styles both).
    - the AI chat helper (Alt+Shift+C), only while the AI assistant is switched on and set up in Admin → AI assistant.
      It uses the provider and model made active there (App\Ai\Assistant::chat). Signed-in people opt in once.
    Both need JavaScript, so the launchers stay hidden until js/widgets.js shows them.
--}}
@php
    $swUser = auth()->user();
    $swChatOn = \Illuminate\Support\Facades\Route::has('chatbot.message') && \App\Ai\Assistant::unavailableReason() === null;
    $swConsented = $swChatOn && $swUser ? app(\App\Ai\Assistant::class)->hasConsent($swUser) : false;
    $swProvider = $swChatOn ? \App\Ai\Assistant::providerLabel() : '';
    $swToggles = [
        'links' => ['Highlight links', 'fa-link'],
        'headings' => ['Highlight headings', 'fa-heading'],
        'focus' => ['Strong focus outline', 'fa-crosshairs'],
        'cursor' => ['Big cursor', 'fa-arrow-pointer'],
        'motion' => ['Stop animations', 'fa-circle-pause'],
        'images' => ['Hide images', 'fa-image'],
    ];
    $swColours = [
        '' => ['Default', 'fa-palette'],
        'contrast' => ['High contrast', 'fa-circle-half-stroke'],
        'dark-contrast' => ['Dark contrast', 'fa-moon'],
        'invert' => ['Invert colours', 'fa-repeat'],
        'grayscale' => ['Greyscale', 'fa-droplet-slash'],
        'low-sat' => ['Soft colours', 'fa-droplet'],
    ];
@endphp
<div class="sw-launchers" data-site-widgets hidden>
    @if ($swChatOn)
        <button type="button" class="sw-launcher sw-launcher-chat" data-sw-open="sw-chat" aria-controls="sw-chat" aria-expanded="false" aria-keyshortcuts="Alt+Shift+C">
            <i class="fas fa-comments" aria-hidden="true"></i><span class="visually-hidden">Open the AI help chat</span>
        </button>
    @endif
    <button type="button" class="sw-launcher sw-launcher-a11y" data-sw-open="sw-a11y" aria-controls="sw-a11y" aria-expanded="false" aria-keyshortcuts="Alt+Shift+A">
        <i class="fas fa-universal-access" aria-hidden="true"></i><span class="visually-hidden">Open accessibility settings</span>
    </button>
</div>

{{-- Accessibility panel --}}
<section class="sw-panel sw-a11y" id="sw-a11y" role="dialog" aria-modal="false" aria-labelledby="sw-a11y-title" data-a11y-panel hidden>
    <header class="sw-panel-head">
        <h2 class="sw-panel-title" id="sw-a11y-title" tabindex="-1"><i class="fas fa-universal-access" aria-hidden="true"></i> Accessibility</h2>
        <button type="button" class="sw-icon-btn" data-sw-close><i class="fas fa-xmark" aria-hidden="true"></i><span class="visually-hidden">Close accessibility settings</span></button>
    </header>
    <div class="sw-panel-body">
        <p class="sw-note">Changes apply straight away and are saved on this device. Press <kbd>Alt</kbd>+<kbd>Shift</kbd>+<kbd>A</kbd> to open this panel from any page.</p>

        <h3 class="sw-section">Quick profiles</h3>
        <div class="sw-grid sw-grid-profiles">
            <button type="button" class="sw-tile" data-a11y-profile="vision" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i><span>Low vision</span></button>
            <button type="button" class="sw-tile" data-a11y-profile="dyslexia" aria-pressed="false"><i class="fas fa-book-open-reader" aria-hidden="true"></i><span>Dyslexia friendly</span></button>
            <button type="button" class="sw-tile" data-a11y-profile="focus" aria-pressed="false"><i class="fas fa-bullseye" aria-hidden="true"></i><span>Focus (ADHD)</span></button>
            <button type="button" class="sw-tile" data-a11y-profile="seizure" aria-pressed="false"><i class="fas fa-bolt" aria-hidden="true"></i><span>Seizure safe</span></button>
            <button type="button" class="sw-tile" data-a11y-profile="motor" aria-pressed="false"><i class="fas fa-keyboard" aria-hidden="true"></i><span>Keyboard &amp; motor</span></button>
            <button type="button" class="sw-tile" data-a11y-profile="blind" aria-pressed="false"><i class="fas fa-volume-high" aria-hidden="true"></i><span>Read aloud</span></button>
        </div>

        <h3 class="sw-section">Text</h3>
        @foreach (['text' => ['Text size', ['100%', '115%', '130%', '150%', '175%']], 'line' => ['Line spacing', ['Default', 'Wider', 'Widest']], 'spacing' => ['Letter spacing', ['Default', 'Wider', 'Widest']]] as $swStep => [$swLabel, $swValues])
            <div class="sw-stepper" data-a11y-step="{{ $swStep }}" data-values="{{ implode('|', $swValues) }}">
                <span class="sw-stepper-label" id="sw-step-{{ $swStep }}">{{ $swLabel }}</span>
                <button type="button" class="sw-icon-btn" data-dir="-1" aria-describedby="sw-step-{{ $swStep }}"><i class="fas fa-minus" aria-hidden="true"></i><span class="visually-hidden">Decrease {{ strtolower($swLabel) }}</span></button>
                <output class="sw-stepper-value" aria-live="polite" aria-labelledby="sw-step-{{ $swStep }}">{{ $swValues[0] }}</output>
                <button type="button" class="sw-icon-btn" data-dir="1" aria-describedby="sw-step-{{ $swStep }}"><i class="fas fa-plus" aria-hidden="true"></i><span class="visually-hidden">Increase {{ strtolower($swLabel) }}</span></button>
            </div>
        @endforeach
        <div class="sw-grid">
            <button type="button" class="sw-tile" data-a11y-toggle="font" aria-pressed="false"><i class="fas fa-font" aria-hidden="true"></i><span>Readable font</span></button>
            <button type="button" class="sw-tile" data-a11y-toggle="align" aria-pressed="false"><i class="fas fa-align-left" aria-hidden="true"></i><span>Align text left</span></button>
        </div>

        <h3 class="sw-section" id="sw-colour-heading">Colour and contrast</h3>
        <div class="sw-grid" role="group" aria-labelledby="sw-colour-heading">
            @foreach ($swColours as $swValue => [$swLabel, $swIcon])
                <button type="button" class="sw-tile" data-a11y-choice="color" data-value="{{ $swValue }}" aria-pressed="{{ $swValue === '' ? 'true' : 'false' }}"><i class="fas {{ $swIcon }}" aria-hidden="true"></i><span>{{ $swLabel }}</span></button>
            @endforeach
        </div>

        <h3 class="sw-section">Reading and navigation</h3>
        <div class="sw-grid">
            @foreach ($swToggles as $swKey => [$swLabel, $swIcon])
                <button type="button" class="sw-tile" data-a11y-toggle="{{ $swKey }}" aria-pressed="false"><i class="fas {{ $swIcon }}" aria-hidden="true"></i><span>{{ $swLabel }}</span></button>
            @endforeach
            <button type="button" class="sw-tile" data-a11y-choice="guide" data-value="line" data-allow-none aria-pressed="false"><i class="fas fa-ruler-horizontal" aria-hidden="true"></i><span>Reading guide</span></button>
            <button type="button" class="sw-tile" data-a11y-choice="guide" data-value="mask" data-allow-none aria-pressed="false"><i class="fas fa-window-maximize" aria-hidden="true"></i><span>Reading mask</span></button>
        </div>

        <h3 class="sw-section">Read aloud</h3>
        <div data-tts>
            <p class="sw-note" data-tts-unsupported hidden>This browser cannot read pages aloud. A screen reader such as NVDA, VoiceOver or TalkBack will work instead.</p>
            <div class="sw-row" data-tts-controls>
                <button type="button" class="btn btn-primary btn-sm" data-tts-action="page"><i class="fas fa-play" aria-hidden="true"></i> Read this page</button>
                <button type="button" class="btn btn-secondary btn-sm" data-tts-action="selection"><i class="fas fa-i-cursor" aria-hidden="true"></i> Read selected text</button>
                <button type="button" class="btn btn-secondary btn-sm" data-tts-action="pause" aria-pressed="false"><i class="fas fa-pause" aria-hidden="true"></i> Pause</button>
                <button type="button" class="btn btn-secondary btn-sm" data-tts-action="stop"><i class="fas fa-stop" aria-hidden="true"></i> Stop</button>
            </div>
            <div class="sw-row" data-tts-controls>
                <label class="sw-inline-field" for="sw-tts-rate">Speed
                    <select id="sw-tts-rate" data-a11y-select="rate">
                        <option value="0.7">Slow</option>
                        <option value="1" selected>Normal</option>
                        <option value="1.3">Fast</option>
                        <option value="1.6">Very fast</option>
                    </select>
                </label>
                <label class="sw-inline-field" for="sw-tts-voice">Voice
                    <select id="sw-tts-voice" data-a11y-select="voice"><option value="">Device default</option></select>
                </label>
            </div>
            <div class="sw-grid" data-tts-controls>
                <button type="button" class="sw-tile" data-a11y-toggle="clickread" aria-pressed="false"><i class="fas fa-hand-pointer" aria-hidden="true"></i><span>Read text I click</span></button>
            </div>
        </div>

        <div class="sw-panel-foot">
            <button type="button" class="btn btn-secondary btn-sm" data-a11y-reset><i class="fas fa-rotate-left" aria-hidden="true"></i> Reset all settings</button>
            <a href="{{ \Illuminate\Support\Facades\Route::has('contact') ? route('contact') : url('/') }}" class="sw-link">Report an accessibility problem</a>
        </div>
        <p class="visually-hidden" aria-live="polite" data-a11y-status></p>
    </div>
</section>

{{-- AI chat helper --}}
@if ($swChatOn)
    <section class="sw-panel sw-chat" id="sw-chat" role="dialog" aria-modal="false" aria-labelledby="sw-chat-title" data-chat hidden
             data-user="{{ $swUser?->id ?? 'guest' }}"
             @if ($swUser) data-endpoint="{{ route('chatbot.message') }}" data-consent-endpoint="{{ route('chatbot.consent') }}" @endif
             data-max="{{ \App\Ai\Assistant::CHAT_MESSAGE_CHARS }}" data-history="{{ \App\Ai\Assistant::CHAT_HISTORY_MESSAGES }}">
        <header class="sw-panel-head">
            <h2 class="sw-panel-title" id="sw-chat-title" tabindex="-1"><i class="fas fa-comments" aria-hidden="true"></i> Ask {{ $brand['site_name'] ?? 'Hive' }} <span class="badge badge-scheduled">AI</span></h2>
            @if ($swUser)
                <button type="button" class="sw-icon-btn" data-chat-autoread aria-pressed="false" title="Read answers aloud"><i class="fas fa-volume-high" aria-hidden="true"></i><span class="visually-hidden">Read answers aloud automatically</span></button>
                <button type="button" class="sw-icon-btn" data-chat-clear title="Start a new conversation"><i class="fas fa-trash-can" aria-hidden="true"></i><span class="visually-hidden">Clear the conversation</span></button>
            @endif
            <button type="button" class="sw-icon-btn" data-sw-close><i class="fas fa-xmark" aria-hidden="true"></i><span class="visually-hidden">Close the chat</span></button>
        </header>

        @if (! $swUser)
            <div class="sw-panel-body">
                <p>The AI help assistant answers questions about using the platform. Sign in to use it.</p>
                @if (\Illuminate\Support\Facades\Route::has('login'))
                    <a class="btn btn-primary btn-sm" href="{{ route('login') }}"><i class="fas fa-right-to-bracket" aria-hidden="true"></i> Sign in</a>
                @endif
            </div>
        @else
            <div class="sw-chat-consent sw-panel-body" data-chat-consent @if ($swConsented) hidden @endif>
                <p>AI chat is off for your account. When you send a message, we send it, the earlier messages in this chat and the title of the page you are on to <strong>{{ $swProvider }}</strong> to write an answer. We don’t keep the conversation: it stays in this browser tab until you close it or clear it.</p>
                <p>Don’t type passwords, codes, bank details or other private information. AI can be wrong: check important answers.</p>
                <button type="button" class="btn btn-primary btn-sm" data-chat-grant><i class="fas fa-toggle-on" aria-hidden="true"></i> Turn on AI chat</button>
            </div>
            <div class="sw-chat-main" data-chat-main @unless ($swConsented) hidden @endunless>
                <div class="sw-chat-log" role="log" aria-live="polite" aria-relevant="additions" aria-label="Conversation" tabindex="0" data-chat-log>
                    <p class="sw-chat-msg is-assistant sw-chat-welcome">Hello {{ \Illuminate\Support\Str::of($swUser->name)->explode(' ')->first() }}. I can explain how the platform works and where to find things. What would you like to know?</p>
                </div>
                <div class="sw-chat-suggestions" data-chat-suggestions>
                    <button type="button" class="sw-chip" data-chat-suggest>How do I register my startup?</button>
                    <button type="button" class="sw-chip" data-chat-suggest>How does the growth assessment work?</button>
                    <button type="button" class="sw-chip" data-chat-suggest>How can I make the text bigger?</button>
                </div>
                <p class="sw-chat-status" aria-live="assertive" data-chat-status></p>
                <form class="sw-chat-form" data-chat-form>
                    <label for="sw-chat-input" class="visually-hidden">Your message</label>
                    <textarea id="sw-chat-input" rows="2" maxlength="{{ \App\Ai\Assistant::CHAT_MESSAGE_CHARS }}" placeholder="Type your question…" aria-describedby="sw-chat-hint" data-chat-input></textarea>
                    <button type="submit" class="btn btn-primary" data-chat-send><i class="fas fa-paper-plane" aria-hidden="true"></i><span class="visually-hidden">Send</span></button>
                </form>
                <p class="sw-chat-foot" id="sw-chat-hint">Enter to send, Shift+Enter for a new line · Sent to {{ $swProvider }} · not stored ·
                    <button type="button" class="sw-link" data-chat-revoke>Turn off AI chat</button></p>
            </div>
        @endif
    </section>
@endif
