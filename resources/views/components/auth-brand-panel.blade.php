@php
    $appName = 'LawDocs';
    $tagline = 'Precedent-driven document assembly for your practice.';

    // Fixed single theme (deep slate/indigo) — no admin-configurable theme
    // picker, unlike wma-bot's version. Simple, professional, one look.
    $start  = '15 23 42';   // slate-900
    $mid    = '30 27 75';   // indigo-950
    $end    = '49 46 129';  // indigo-900
    $accentRgb = '129 140 248'; // indigo-400
    $accent = "rgb({$accentRgb})";

    [$ar, $ag, $ab] = explode(' ', $accentRgb);
    $a08 = "rgba({$ar},{$ag},{$ab},.08)";
    $a12 = "rgba({$ar},{$ag},{$ab},.12)";
    $a15 = "rgba({$ar},{$ag},{$ab},.15)";
    $a18 = "rgba({$ar},{$ag},{$ab},.18)";
    $a22 = "rgba({$ar},{$ag},{$ab},.22)";
    $a25 = "rgba({$ar},{$ag},{$ab},.25)";
    $a30 = "rgba({$ar},{$ag},{$ab},.30)";
    $a35 = "rgba({$ar},{$ag},{$ab},.35)";
    $a40 = "rgba({$ar},{$ag},{$ab},.40)";
    $a45 = "rgba({$ar},{$ag},{$ab},.45)";
    $a55 = "rgba({$ar},{$ag},{$ab},.55)";

    $panelBackground = "linear-gradient(145deg, rgb({$start}) 0%, rgb({$mid}) 40%, rgb({$end}) 100%)";
    $panelText    = 'rgb(255 255 255)';
    $panelMuted   = 'rgba(255,255,255,.52)';
    $badgeBg      = 'rgba(255,255,255,.06)';
    $badgeBorder  = 'rgba(255,255,255,.10)';
    $featureText  = 'rgba(255,255,255,.68)';
    $dividerColor = 'rgba(255,255,255,.08)';
    $footerText   = 'rgba(255,255,255,.32)';

    $features = [
        'Upload a precedent once, tag its reusable clauses.',
        'Staff answer a short questionnaire per matter.',
        'A tailored .docx drops out — exact wording, exact formatting.',
    ];
@endphp

{{-- ── Layout CSS ──────────────────────────────────────────────────────────── --}}
<style>
    @media (min-width: 1024px) {
        .auth-brand-panel {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 52% !important;
            height: 100vh !important;
            z-index: 20;
        }
        .fi-simple-main {
            max-width: 100vw !important;
            width: 100% !important;
            margin: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 0 !important;
            background: transparent !important;
            ring: none !important;
            min-height: 100vh;
        }
        .fi-simple-main.ring-1 { --tw-ring-shadow: none !important; }
        .fi-simple-page {
            min-height: 100vh;
            padding-left: 52% !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding-top: 3rem;
            padding-bottom: 3rem;
            padding-right: 2rem;
            box-sizing: border-box;
        }
        .fi-simple-page > *:not(.auth-brand-panel) {
            max-width: 22rem;
            width: 100%;
        }
    }
    @media (max-width: 1023px) {
        .auth-brand-panel { display: none !important; }
    }

    @keyframes abp-node-pulse {
        0%, 100% { opacity: .55; r: 9; }
        50%       { opacity: .85; r: 11; }
    }
    @keyframes abp-ring-spin {
        from { transform-origin: 300px 110px; transform: rotate(0deg); }
        to   { transform-origin: 300px 110px; transform: rotate(360deg); }
    }
    @keyframes abp-ring-spin-rev {
        from { transform-origin: 300px 110px; transform: rotate(0deg); }
        to   { transform-origin: 300px 110px; transform: rotate(-360deg); }
    }
    .abp-ring-1 { animation: abp-ring-spin 38s linear infinite; }
    .abp-ring-2 { animation: abp-ring-spin-rev 52s linear infinite; }
    .abp-center-dot { animation: abp-node-pulse 2.8s ease-in-out infinite; }
</style>

<div
    class="auth-brand-panel"
    style="
        position: relative;
        overflow: hidden;
        background: {{ $panelBackground }};
        color: {{ $panelText }};
        display: flex;
        flex-direction: column;
        padding: 2.5rem 3rem;
    "
>
    {{-- ── Background layers ──────────────────────────────────────────────── --}}

    <div style="position:absolute;inset:0;background-image:radial-gradient(circle, {{ $a18 }} 1px, transparent 1px);background-size:30px 30px;pointer-events:none;"></div>

    <div style="position:absolute;inset:0;opacity:.025;background-image:url(&quot;data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E&quot;);background-size:200px 200px;pointer-events:none;"></div>

    <div style="position:absolute;top:-10rem;right:-5rem;width:32rem;height:32rem;border-radius:9999px;background:{{ $accent }};opacity:.13;filter:blur(90px);pointer-events:none;"></div>
    <div style="position:absolute;bottom:-12rem;left:-8rem;width:38rem;height:38rem;border-radius:9999px;background:{{ $accent }};opacity:.09;filter:blur(120px);pointer-events:none;"></div>

    {{-- ── Content ─────────────────────────────────────────────────────────── --}}
    <div style="position:relative;z-index:1;display:flex;flex-direction:column;height:100%;gap:0;">

        {{-- ── Logo row ────────────────────────────────────────────────────── --}}
        <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0;">
            <div style="display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border-radius:.625rem;background:{{ $accent }};font-size:1.1rem;font-weight:800;color:#fff;flex-shrink:0;box-shadow:0 4px 16px {{ $a45 }};">
                {{ strtoupper(substr($appName, 0, 1)) }}
            </div>
            <span style="font-size:1.1rem;font-weight:700;letter-spacing:-.02em;color:{{ $panelText }};">{{ $appName }}</span>
            <span style="margin-left:auto;font-size:.62rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.55);background:{{ $badgeBg }};border:1px solid {{ $badgeBorder }};padding:.28rem .7rem;border-radius:9999px;">
                Admin
            </span>
        </div>

        {{-- ── Middle: illustration + text ─────────────────────────────────── --}}
        <div style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:1.25rem;padding:.5rem 0;">

            {{-- ── Constellation SVG ──────────────────────────────────────── --}}
            <div style="width:100%;line-height:0;">
                <svg viewBox="0 0 600 190" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:88%;height:auto;display:block;margin:0 auto;">
                    <circle class="abp-ring-2" cx="300" cy="95" r="88" stroke="{{ $a12 }}" stroke-width="1" stroke-dasharray="3 9"/>
                    <circle class="abp-ring-1" cx="300" cy="95" r="60" stroke="{{ $a18 }}" stroke-width="1" stroke-dasharray="5 6"/>
                    <circle cx="300" cy="95" r="34" stroke="{{ $a35 }}" stroke-width="1.5"/>
                    <circle cx="300" cy="95" r="22" fill="{{ $a12 }}"/>
                    <circle cx="300" cy="95" r="13" fill="{{ $a25 }}"/>
                    <circle class="abp-center-dot" cx="300" cy="95" r="9" fill="{{ $a55 }}"/>
                    <line x1="300" y1="88" x2="300" y2="102" stroke="rgba(255,255,255,.55)" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="293" y1="95" x2="307" y2="95" stroke="rgba(255,255,255,.55)" stroke-width="1.5" stroke-linecap="round"/>

                    <circle cx="334" cy="95" r="5.5" fill="{{ $a45 }}"/>
                    <line x1="313" y1="95" x2="329" y2="95" stroke="{{ $a25 }}" stroke-width="1"/>
                    <circle cx="317" cy="125" r="5" fill="{{ $a40 }}"/>
                    <line x1="305" y1="112" x2="314" y2="122" stroke="{{ $a25 }}" stroke-width="1"/>
                    <circle cx="283" cy="125" r="5" fill="{{ $a40 }}"/>
                    <line x1="295" y1="112" x2="286" y2="122" stroke="{{ $a25 }}" stroke-width="1"/>
                    <circle cx="266" cy="95" r="5.5" fill="{{ $a45 }}"/>
                    <line x1="287" y1="95" x2="271" y2="95" stroke="{{ $a25 }}" stroke-width="1"/>
                    <circle cx="283" cy="65" r="5" fill="{{ $a40 }}"/>
                    <line x1="295" y1="78" x2="286" y2="68" stroke="{{ $a25 }}" stroke-width="1"/>
                    <circle cx="317" cy="65" r="5" fill="{{ $a40 }}"/>
                    <line x1="305" y1="78" x2="314" y2="68" stroke="{{ $a25 }}" stroke-width="1"/>

                    <circle cx="360" cy="95" r="7" fill="{{ $a25 }}" stroke="{{ $a35 }}" stroke-width="1"/>
                    <line x1="340" y1="95" x2="353" y2="95" stroke="{{ $a12 }}" stroke-width="0.8" stroke-dasharray="2 3"/>
                    <circle cx="330" cy="148" r="6" fill="{{ $a22 }}" stroke="{{ $a30 }}" stroke-width="1"/>
                    <line x1="316" y1="128" x2="325" y2="143" stroke="{{ $a12 }}" stroke-width="0.8" stroke-dasharray="2 3"/>
                    <circle cx="240" cy="95" r="7" fill="{{ $a25 }}" stroke="{{ $a35 }}" stroke-width="1"/>
                    <line x1="260" y1="95" x2="247" y2="95" stroke="{{ $a12 }}" stroke-width="0.8" stroke-dasharray="2 3"/>
                    <circle cx="270" cy="148" r="6" fill="{{ $a22 }}" stroke="{{ $a30 }}" stroke-width="1"/>
                    <line x1="284" y1="128" x2="275" y2="143" stroke="{{ $a12 }}" stroke-width="0.8" stroke-dasharray="2 3"/>
                    <circle cx="300" cy="35" r="7" fill="{{ $a25 }}" stroke="{{ $a35 }}" stroke-width="1"/>
                    <line x1="300" y1="61" x2="300" y2="42" stroke="{{ $a12 }}" stroke-width="0.8" stroke-dasharray="2 3"/>

                    <circle cx="420" cy="55" r="4" fill="{{ $a18 }}"/>
                    <circle cx="450" cy="100" r="3" fill="{{ $a12 }}"/>
                    <circle cx="415" cy="152" r="4.5" fill="{{ $a15 }}"/>
                    <circle cx="180" cy="55" r="4" fill="{{ $a18 }}"/>
                    <circle cx="148" cy="100" r="3" fill="{{ $a12 }}"/>
                    <circle cx="185" cy="152" r="4.5" fill="{{ $a15 }}"/>
                    <circle cx="300" cy="185" r="3" fill="{{ $a12 }}"/>
                    <circle cx="510" cy="80" r="2.5" fill="{{ $a08 }}"/>
                    <circle cx="90" cy="80" r="2.5" fill="{{ $a08 }}"/>

                    <line x1="367" y1="95" x2="416" y2="68" stroke="{{ $a08 }}" stroke-width="0.8" stroke-dasharray="2 5"/>
                    <line x1="233" y1="95" x2="184" y2="68" stroke="{{ $a08 }}" stroke-width="0.8" stroke-dasharray="2 5"/>
                    <line x1="335" y1="154" x2="418" y2="150" stroke="{{ $a08 }}" stroke-width="0.8" stroke-dasharray="2 5"/>
                    <line x1="265" y1="154" x2="182" y2="150" stroke="{{ $a08 }}" stroke-width="0.8" stroke-dasharray="2 5"/>
                </svg>
            </div>

            {{-- ── Headline ────────────────────────────────────────────────── --}}
            <div style="display:flex;flex-direction:column;gap:.55rem;">
                <h2 style="font-size:clamp(1.8rem,3vw,2.4rem);font-weight:800;letter-spacing:-.04em;line-height:1.13;color:{{ $panelText }};margin:0;">
                    Draft with <span style="color:{{ $accent }};">confidence</span>
                </h2>
                <p style="font-size:.9rem;color:{{ $panelMuted }};line-height:1.6;max-width:28rem;margin:0;">{{ $tagline }}</p>
            </div>

            {{-- ── Divider ─────────────────────────────────────────────────── --}}
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="flex:1;height:1px;background:linear-gradient(to right, {{ $accent }}, {{ $dividerColor }});opacity:.35;"></div>
                <svg viewBox="0 0 8 8" fill="{{ $accent }}" style="width:.5rem;height:.5rem;opacity:.5;flex-shrink:0;"><circle cx="4" cy="4" r="4"/></svg>
                <div style="flex:1;height:1px;background:linear-gradient(to left, {{ $accent }}, {{ $dividerColor }});opacity:.35;"></div>
            </div>

            {{-- ── Numbered features ───────────────────────────────────────── --}}
            <div style="display:flex;flex-direction:column;gap:.6rem;">
                @foreach ($features as $i => $feature)
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <span style="font-size:.68rem;font-weight:700;letter-spacing:.06em;color:{{ $accent }};opacity:.75;font-variant-numeric:tabular-nums;min-width:1.6rem;flex-shrink:0;">0{{ $i + 1 }}</span>
                        <div style="width:2rem;height:1px;background:{{ $dividerColor }};flex-shrink:0;"></div>
                        <span style="font-size:.875rem;color:{{ $featureText }};line-height:1.45;">{{ $feature }}</span>
                    </div>
                @endforeach
            </div>

        </div>

        {{-- ── Footer ───────────────────────────────────────────────────────── --}}
        <div style="display:flex;align-items:center;gap:.5rem;font-size:.72rem;color:{{ $footerText }};flex-shrink:0;">
            <span style="display:inline-flex;gap:.22rem;align-items:center;">
                <span style="width:.38rem;height:.38rem;border-radius:50%;background:{{ $accent }};opacity:.6;display:inline-block;"></span>
                <span style="width:.38rem;height:.38rem;border-radius:50%;background:{{ $accent }};opacity:.35;display:inline-block;"></span>
                <span style="width:.38rem;height:.38rem;border-radius:50%;background:{{ $accent }};opacity:.16;display:inline-block;"></span>
            </span>
            LawDocs Admin
        </div>

    </div>
</div>
