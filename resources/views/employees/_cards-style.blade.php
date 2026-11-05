{{-- Gaya kartu orang (.em-*): dipakai layar Karyawan dan Penerima. --}}
    <style>
        .em-top { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 12px; }
        .em-spacer { flex: 1; }
        .em-pills { display: inline-flex; gap: 6px; flex-wrap: wrap; }
        .em-pill { border: 0; border-radius: 999px; padding: 6px 14px; font: inherit; font-size: 12.5px; cursor: pointer; background: var(--dl-chip, #eee); color: var(--dl-ink, #333); }
        .em-pill b { margin-left: 4px; background: rgba(255,255,255,.55); border-radius: 999px; padding: 0 7px; font-weight: 700; }
        .em-pill--blue.is-active { background: #2f6fd0; color: #fff; } .em-pill--coral.is-active { background: #d85a30; color: #fff; } .em-pill--gray.is-active { background: #6b6b66; color: #fff; }
        .em-pill:not(.is-active).em-pill--blue { background: #e3edfb; color: #1c4a96; } .em-pill:not(.is-active).em-pill--coral { background: #fbe6de; color: #8a3414; } .em-pill:not(.is-active).em-pill--gray { background: #ececea; color: #4a4a47; }
        .em-search { padding: 6px 10px; border: 1px solid var(--dl-border, #ccc); border-radius: 8px; background: var(--dl-surface, #fff); color: var(--dl-ink, #222); font: inherit; font-size: 12.5px; min-width: 200px; }
        .em-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; }
        .em-card { position: relative; display: flex; flex-direction: column; gap: 4px; padding: 0 14px 12px; background: var(--dl-surface, #fff); border: 1px solid var(--dl-border, #e2e2e2); border-radius: 14px; text-decoration: none; color: inherit; overflow: hidden; transition: box-shadow .15s, transform .15s; }
        .em-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,.12); transform: translateY(-2px); }
        .em-card[hidden] { display: none; }
        .em-card.is-exited { opacity: .72; }
        .em-bar { display: block; height: 46px; margin: 0 -14px; }
        .em-photo { width: 68px; height: 68px; border-radius: 50%; object-fit: cover; border: 3px solid var(--dl-surface, #fff); margin-top: -34px; background: #e8eef3; display: block; }
        .em-photo--ini { display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 22px; }
        .em-name { font-weight: 700; font-size: 15px; margin-top: 4px; color: var(--dl-ink-strong, #111); }
        .em-sub { font-size: 12px; color: var(--dl-muted, #666); }
        .em-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
        .em-chip { font-size: 11px; border-radius: 999px; padding: 2px 9px; background: #eee; color: #333; }
        .em-chip--ok { background: #e2f2e0; color: #1f5a1b; } .em-chip--warn { background: #fbefd5; color: #7a4d06; } .em-chip--info { background: #e1ecf9; color: #1c4a96; } .em-chip--violet { background: #ece9fa; color: #473f9c; }
        .em-chip--gray { background: #ececea; color: #4a4a47; } .em-chip--red { background: #fbe0e0; color: #8e1f1f; font-weight: 700; }
        .em-warn { font-size: 11.5px; border-radius: 8px; padding: 3px 8px; background: #fbefd5; color: #7a4d06; margin-top: 2px; }
        .em-warn--red { background: #fbe0e0; color: #8e1f1f; }
        .em-foot { font-size: 11.5px; color: var(--dl-muted, #666); margin-top: 4px; border-top: 1px solid var(--dl-border, #eee); padding-top: 6px; }
        .em-gphoto { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px; border: 1px solid var(--dl-border, #ddd); }
        [data-dl^="x-dl::grid id=employees"] .dl-avatar-id { vertical-align: middle; }
        @media (max-width: 600px) { .em-cards { grid-template-columns: 1fr; } .em-search { width: 100%; } }
    </style>
