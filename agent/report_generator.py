import html
from typing import Dict, Any

class ReportGenerator:
    """
    Genera un report HTML self-contained per l'esecuzione di WinDeploy.
    Design professionale, nessuna dipendenza da file esterni o CDN.
    """

    @staticmethod
    def generate(data: Dict[str, Any]) -> str:
        # Estrazione e sanitizzazione dati per prevenire XSS (se i dati arrivassero da fonti non sicure)
        tecnico = html.escape(data.get("tecnico", "N/D"))
        data_ora = html.escape(data.get("data_ora", "N/D"))
        codice_wizard = html.escape(data.get("codice_wizard", "N/D"))
        durata = html.escape(data.get("durata", "N/D"))
        
        pc = data.get("pc", {})
        nome_orig = html.escape(pc.get("nome_originale", "N/D"))
        nome_nuovo = html.escape(pc.get("nome_nuovo", "N/D"))
        cpu = html.escape(pc.get("cpu", "N/D"))
        ram = pc.get("ram_gb", "N/D")
        disco = pc.get("disco_gb", "N/D")
        os_ver = html.escape(pc.get("windows", "N/D"))

        steps = data.get("steps", [])
        software = data.get("software_installati", [])
        rimosse = data.get("app_rimosse", [])
        power_plan = data.get("power_plan", {})
        agent_version = html.escape(data.get("agent_version", "1.0.0"))

        # Calcolo contatori
        ok_count = sum(1 for s in steps if s.get("stato") == "ok")
        err_count = sum(1 for s in steps if s.get("stato") == "errore")
        warn_count = sum(1 for s in steps if s.get("stato") == "avviso")

        def get_badge(status: str) -> str:
            status_low = status.lower()
            if status_low == "ok":
                return '<span class="badge badge-success">Completato</span>'
            elif status_low == "errore":
                return '<span class="badge badge-error">Errore</span>'
            elif status_low == "avviso":
                return '<span class="badge badge-warning">Avviso</span>'
            return f'<span class="badge badge-neutral">{html.escape(status)}</span>'

        # Generazione righe tabella Steps
        steps_html = ""
        for i, step in enumerate(steps, 1):
            s_name = html.escape(step.get("nome", ""))
            s_stat = step.get("stato", "")
            s_det = html.escape(step.get("dettaglio", ""))
            s_time = html.escape(step.get("timestamp", ""))
            
            row_class = f"row-{s_stat.lower()}"
            steps_html += f"""
            <tr class="{row_class}">
                <td style="text-align:center;">{i}</td>
                <td><strong>{s_name}</strong></td>
                <td style="text-align:center;">{get_badge(s_stat)}</td>
                <td>{s_det}</td>
                <td class="text-sm">{s_time}</td>
            </tr>
            """

        # Generazione righe tabella Software
        sw_html = ""
        if software:
            for sw in software:
                sw_html += f"""
                <tr>
                    <td>{html.escape(sw.get("nome", ""))}</td>
                    <td>{html.escape(sw.get("versione", ""))}</td>
                    <td>{html.escape(sw.get("metodo", ""))}</td>
                    <td style="text-align:center;">{get_badge(sw.get("esito", ""))}</td>
                </tr>
                """
        else:
            sw_html = "<tr><td colspan='4' style='text-align:center;'>Nessun software installato</td></tr>"

        # Generazione righe tabella App Rimosse
        rm_html = ""
        if rimosse:
            for rm in rimosse:
                rm_html += f"""
                <tr>
                    <td>{html.escape(rm.get("nome", ""))}</td>
                    <td>{html.escape(rm.get("versione", ""))}</td>
                    <td style="text-align:center;">{get_badge(rm.get("esito", ""))}</td>
                </tr>
                """
        else:
            rm_html = "<tr><td colspan='3' style='text-align:center;'>Nessuna app rimossa</td></tr>"

        # Generazione Power Plan
        pp_html = "<ul class='info-list'>"
        for k, v in power_plan.items():
            pp_html += f"<li><strong>{html.escape(str(k).replace('_', ' ').title())}:</strong> {html.escape(str(v))}</li>"
        pp_html += "</ul>"

        # HTML completo con CSS inline
        return f"""<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report WinDeploy - {nome_nuovo}</title>
    <style>
        :root {{
            --primary: #1E3A5F;
            --bg-body: #F3F4F6;
            --bg-card: #FFFFFF;
            --text-main: #1F2937;
            --border: #E5E7EB;
            --success: #059669;
            --success-bg: #D1FAE5;
            --error: #DC2626;
            --error-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
        }}
        * {{ box-sizing: border-box; }}
        body {{
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0; padding: 20px; line-height: 1.5;
        }}
        .container {{ max-width: 1000px; margin: 0 auto; }}
        .header {{
            background-color: var(--primary);
            color: white;
            padding: 20px 30px;
            border-radius: 8px 8px 0 0;
            display: flex; justify-content: space-between; align-items: center;
        }}
        .header h1 {{ margin: 0; font-size: 24px; }}
        .header-meta {{ text-align: right; font-size: 14px; opacity: 0.9; }}
        .card {{
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 24px;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }}
        .card h2 {{
            margin-top: 0; color: var(--primary);
            border-bottom: 2px solid var(--border);
            padding-bottom: 10px; font-size: 18px;
        }}
        .grid-2 {{ display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }}
        
        .info-list {{ list-style: none; padding: 0; margin: 0; }}
        .info-list li {{ padding: 8px 0; border-bottom: 1px solid #F9FAFB; }}
        .info-list li:last-child {{ border-bottom: none; }}
        .info-list strong {{ color: #4B5563; display: inline-block; width: 140px; }}
        
        .stats {{ display: flex; gap: 15px; margin-bottom: 15px; }}
        .stat-box {{
            flex: 1; padding: 15px; border-radius: 6px;
            text-align: center; font-weight: bold; font-size: 18px; border: 1px solid;
        }}
        .stat-box.success {{ background-color: var(--success-bg); color: var(--success); border-color: #A7F3D0; }}
        .stat-box.error {{ background-color: var(--error-bg); color: var(--error); border-color: #FECACA; }}
        .stat-box.warning {{ background-color: var(--warning-bg); color: var(--warning); border-color: #FDE68A; }}
        
        table {{ width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }}
        th, td {{ padding: 12px; border: 1px solid var(--border); text-align: left; }}
        th {{ background-color: #F9FAFB; font-weight: 600; color: var(--primary); }}
        .row-errore {{ background-color: #FEF2F2; }}
        .row-avviso {{ background-color: #FFFBEB; }}
        
        .badge {{
            display: inline-block; padding: 4px 10px; border-radius: 9999px;
            font-size: 12px; font-weight: 600; text-transform: uppercase;
        }}
        .badge-success {{ background-color: var(--success-bg); color: var(--success); }}
        .badge-error {{ background-color: var(--error-bg); color: var(--error); }}
        .badge-warning {{ background-color: var(--warning-bg); color: var(--warning); }}
        .badge-neutral {{ background-color: #F3F4F6; color: #4B5563; }}
        
        .text-sm {{ font-size: 12px; color: #6B7280; }}
        .footer {{
            margin-top: 30px; text-align: center; font-size: 13px;
            color: #6B7280; padding-top: 20px; border-top: 1px solid var(--border);
        }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ WinDeploy</h1>
            <div class="header-meta">
                <div>Tecnico: <strong>{tecnico}</strong></div>
                <div>{data_ora}</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2>Informazioni PC</h2>
                <ul class="info-list">
                    <li><strong>Nome:</strong> <span style="text-decoration:line-through; color:#9CA3AF;">{nome_orig}</span> &rarr; <b>{nome_nuovo}</b></li>
                    <li><strong>OS:</strong> {os_ver}</li>
                    <li><strong>CPU:</strong> {cpu}</li>
                    <li><strong>RAM:</strong> {ram} GB</li>
                    <li><strong>Disco:</strong> {disco} GB</li>
                </ul>
            </div>

            <div class="card">
                <h2>Riepilogo Esecuzione</h2>
                <div class="stats">
                    <div class="stat-box success">Riuscite<br>{ok_count}</div>
                    <div class="stat-box warning">Avvisi<br>{warn_count}</div>
                    <div class="stat-box error">Errori<br>{err_count}</div>
                </div>
                <div style="text-align:center; font-size:14px; color:#4B5563; margin-top:10px;">
                    Durata totale: <strong>{durata}</strong>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Operazioni Eseguite</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;">#</th>
                        <th>Operazione</th>
                        <th style="width:110px; text-align:center;">Stato</th>
                        <th>Dettaglio</th>
                        <th style="width:130px;">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    {steps_html}
                </tbody>
            </table>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2>Software Installati</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Ver.</th>
                            <th>Metodo</th>
                            <th style="text-align:center;">Esito</th>
                        </tr>
                    </thead>
                    <tbody>{sw_html}</tbody>
                </table>
            </div>

            <div class="card">
                <h2>App Rimosse</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Ver.</th>
                            <th style="text-align:center;">Esito</th>
                        </tr>
                    </thead>
                    <tbody>{rm_html}</tbody>
                </table>
                <br>
                <h2>Power Plan Applicato</h2>
                {pp_html}
            </div>
        </div>

        <div class="footer">
            Generato automaticamente da <strong>WinDeploy Agent v{agent_version}</strong> &bull; Codice Wizard: <strong>{codice_wizard}</strong>
        </div>
    </div>
</body>
</html>"""
