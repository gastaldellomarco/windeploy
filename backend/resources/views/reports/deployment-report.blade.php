{{-- Path: backend/resources/views/reports/deployment-report.blade.php --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>WinDeploy Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #0f172a;
            --card: #111827;
            --card-soft: #1e293b;
            --text: #e5e7eb;
            --muted: #94a3b8;
            --line: #334155;
            --ok: #10b981;
            --warn: #f59e0b;
            --err: #ef4444;
            --info: #38bdf8;
            --brand: #0ea5e9;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px;
            background: #020617;
            color: var(--text);
            font: 14px/1.5 Arial, Helvetica, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header, .card {
            background: linear-gradient(180deg, rgba(17,24,39,0.98), rgba(15,23,42,0.98));
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }

        .header {
            padding: 24px;
            margin-bottom: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .brand h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            color: var(--muted);
            margin-top: 6px;
        }

        .status {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .status.ok { background: rgba(16,185,129,.12); color: #a7f3d0; border-color: rgba(16,185,129,.3); }
        .status.warn { background: rgba(245,158,11,.12); color: #fde68a; border-color: rgba(245,158,11,.3); }
        .status.err { background: rgba(239,68,68,.12); color: #fecaca; border-color: rgba(239,68,68,.3); }
        .status.info { background: rgba(14,165,233,.12); color: #bae6fd; border-color: rgba(14,165,233,.3); }

        .meta-grid, .stats-grid, .two-cols {
            display: grid;
            gap: 16px;
        }

        .meta-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .stats-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-top: 18px;
        }

        .two-cols {
            grid-template-columns: 1.2fr .8fr;
            margin-top: 20px;
        }

        .meta-item, .stat, .section-block {
            background: rgba(15,23,42,.7);
            border: 1px solid rgba(51,65,85,.9);
            border-radius: 14px;
            padding: 14px 16px;
        }

        .label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 6px;
        }

        .value {
            font-size: 16px;
            font-weight: 700;
            word-break: break-word;
        }

        .stat .value {
            font-size: 26px;
        }

        .card {
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h2 {
            margin: 0 0 14px 0;
            font-size: 18px;
        }

        .section-block + .section-block {
            margin-top: 12px;
        }

        .timeline {
            display: grid;
            gap: 12px;
        }

        .timeline-item {
            border: 1px solid var(--line);
            background: rgba(15,23,42,.7);
            border-radius: 14px;
            padding: 14px 16px;
        }

        .timeline-top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 6px;
        }

        .timeline-title {
            font-weight: 700;
        }

        .timeline-time {
            color: var(--muted);
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 8px;
        }

        .badge.ok { background: rgba(16,185,129,.12); color: #a7f3d0; }
        .badge.warn { background: rgba(245,158,11,.12); color: #fde68a; }
        .badge.err { background: rgba(239,68,68,.12); color: #fecaca; }
        .badge.info { background: rgba(14,165,233,.12); color: #bae6fd; }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 12px;
        }

        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .footer {
            color: var(--muted);
            text-align: center;
            font-size: 12px;
            margin-top: 18px;
        }

        @media print {
            body {
                background: #fff;
                color: #111827;
                padding: 0;
            }

            .header, .card, .meta-item, .stat, .section-block, .timeline-item {
                box-shadow: none;
                background: #fff;
                color: #111827;
                border-color: #d1d5db;
            }

            .label, .subtitle, .timeline-time, .footer, th {
                color: #6b7280;
            }
        }
    </style>
</head>
<body>
@php
    $reportStatus = strtolower($report['status'] ?? 'sconosciuto');
    $statusClass = str_contains($reportStatus, 'complet') ? 'ok' : (str_contains($reportStatus, 'error') || str_contains($reportStatus, 'errore') ? 'err' : 'info');
@endphp

<div class="container">
    <div class="header">
        <div class="brand">
            <div>
                <h1>WinDeploy Deployment Report</h1>
                <div class="subtitle">Report operativo della configurazione workstation</div>
            </div>
            <div class="status {{ $statusClass }}">
                {{ $report['status'] ?? 'Sconosciuto' }}
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <div class="label">Report ID</div>
                <div class="value">{{ $report['id'] ?? '-' }}</div>
            </div>
            <div class="meta-item">
                <div class="label">Wizard</div>
                <div class="value">{{ $report['wizard_name'] ?? '-' }}</div>
            </div>
            <div class="meta-item">
                <div class="label">PC</div>
                <div class="value">{{ $report['pc_name'] ?? '-' }}</div>
            </div>
            <div class="meta-item">
                <div class="label">Tecnico</div>
                <div class="value">{{ $report['technician_name'] ?? '-' }}</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat">
                <div class="label">Durata</div>
                <div class="value">{{ $report['duration_human'] ?? '-' }}</div>
            </div>
            <div class="stat">
                <div class="label">Installati</div>
                <div class="value">{{ $report['installed_count'] ?? 0 }}</div>
            </div>
            <div class="stat">
                <div class="label">Rimossi</div>
                <div class="value">{{ $report['removed_count'] ?? 0 }}</div>
            </div>
            <div class="stat">
                <div class="label">Errori</div>
                <div class="value">{{ $report['error_count'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    <div class="two-cols">
        <div class="card">
            <h2>Executive summary</h2>
            <div class="section-block">
                La macchina <strong>{{ $report['pc_name'] ?? '-' }}</strong> è stata processata con il wizard
                <strong>{{ $report['wizard_name'] ?? '-' }}</strong>.
                Stato finale: <strong>{{ $report['status'] ?? '-' }}</strong>.
            </div>

            <div class="section-block">
                <div class="label">Dettagli operativi</div>
                <table>
                    <tbody>
                        <tr>
                            <th>Inizio</th>
                            <td>{{ $report['started_at'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Fine</th>
                            <td>{{ $report['completed_at'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>OS</th>
                            <td>{{ $report['windows_version'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>CPU</th>
                            <td>{{ $report['cpu'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>RAM</th>
                            <td>{{ $report['ram_gb'] ?? '-' }} GB</td>
                        </tr>
                        <tr>
                            <th>Disco</th>
                            <td>{{ $report['disk_gb'] ?? '-' }} GB</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Esito tecnico</h2>

            <div class="section-block">
                <div class="label">Software installato</div>
                @if(!empty($report['installed_software']))
                    <ul>
                        @foreach($report['installed_software'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <div>Nessun elemento registrato.</div>
                @endif
            </div>

            <div class="section-block">
                <div class="label">Software rimosso</div>
                @if(!empty($report['removed_software']))
                    <ul>
                        @foreach($report['removed_software'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <div>Nessun elemento registrato.</div>
                @endif
            </div>

            <div class="section-block">
                <div class="label">Errori / warning</div>
                @if(!empty($report['issues']))
                    <ul>
                        @foreach($report['issues'] as $issue)
                            <li>{{ $issue }}</li>
                        @endforeach
                    </ul>
                @else
                    <div>Nessuna anomalia rilevata.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Timeline esecuzione</h2>
        <div class="timeline">
            @forelse(($report['steps'] ?? []) as $step)
                @php
                    $stepStatus = strtolower($step['status'] ?? 'info');
                    $stepClass = str_contains($stepStatus, 'ok') || str_contains($stepStatus, 'complet') ? 'ok' : (str_contains($stepStatus, 'warn') ? 'warn' : (str_contains($stepStatus, 'err') ? 'err' : 'info'));
                @endphp
                <div class="timeline-item">
                    <div class="timeline-top">
                        <div class="timeline-title">{{ $step['name'] ?? 'Step' }}</div>
                        <div class="timeline-time">{{ $step['timestamp'] ?? '-' }}</div>
                    </div>
                    <div>{{ $step['message'] ?? 'Nessun dettaglio disponibile.' }}</div>
                    <div class="badge {{ $stepClass }}">{{ $step['status'] ?? 'info' }}</div>
                </div>
            @empty
                <div class="timeline-item">Nessuno step disponibile.</div>
            @endforelse
        </div>
    </div>

    <div class="footer">
        Generato da WinDeploy · Report HTML operativo
    </div>
</div>
</body>
</html>
