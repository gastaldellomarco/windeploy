# agent/SCHEMA.md

# Riferimento Schema WizardConfig per lo Sviluppo dell'Agent Python

> **Versione schema**: `1.0`
> **File di riferimento normativo**: `docs/schemas/wizard-config.schema.json`
> **Ultimo aggiornamento**: 2026-03-04

---

## Struttura del dict Python

Il dict `wizard_config` è ricevuto dall'endpoint `/api/agent/auth` nella chiave
`wizard_config` del body JSON di risposta. Viene passato ai vari moduli come argomento.

### Accesso ai campi principali

| Campo                             | Tipo Python    | Come accedervi                                            | Obbligatorio            |
| --------------------------------- | -------------- | --------------------------------------------------------- | ----------------------- |
| `version`                         | `str`          | `config["version"]`                                       | ✅ Sì                   |
| `pc_name`                         | `str`          | `config["pc_name"]`                                       | ✅ Sì                   |
| `admin_user`                      | `dict`         | `config["admin_user"]`                                    | ✅ Sì                   |
| `admin_user.username`             | `str`          | `config["admin_user"]["username"]`                        | ✅ Sì                   |
| `admin_user.password_encrypted`   | `str`          | `config["admin_user"]["password_encrypted"]`              | ✅ Sì (in esecuzione)   |
| `admin_user.remove_setup_account` | `bool`         | `config["admin_user"].get("remove_setup_account", False)` | ⚠️ Opzionale            |
| `software`                        | `list[dict]`   | `config["software"]`                                      | ✅ Sì (può essere `[]`) |
| `software[n].id`                  | `int`          | `config["software"][n]["id"]`                             | ✅ Sì                   |
| `software[n].winget_id`           | `str`          | `config["software"][n]["winget_id"]`                      | ✅ Sì                   |
| `software[n].name`                | `str`          | `config["software"][n]["name"]`                           | ✅ Sì                   |
| `software[n].type`                | `str`          | `config["software"][n]["type"]`                           | ✅ Sì                   |
| `software[n].download_url`        | `str \| None`  | `config["software"][n].get("download_url")`               | ⚠️ Opzionale            |
| `bloatware`                       | `list[dict]`   | `config["bloatware"]`                                     | ✅ Sì (può essere `[]`) |
| `bloatware[n].package_name`       | `str`          | `config["bloatware"][n]["package_name"]`                  | ✅ Sì                   |
| `bloatware[n].display_name`       | `str`          | `config["bloatware"][n]["display_name"]`                  | ✅ Sì                   |
| `bloatware[n].selected`           | `bool`         | `config["bloatware"][n]["selected"]`                      | ✅ Sì                   |
| `power_plan`                      | `dict`         | `config["power_plan"]`                                    | ✅ Sì                   |
| `power_plan.type`                 | `str`          | `config["power_plan"]["type"]`                            | ✅ Sì                   |
| `power_plan.screen_timeout_ac`    | `int \| None`  | `config["power_plan"].get("screen_timeout_ac")`           | ⚠️ Opzionale            |
| `power_plan.sleep_timeout_ac`     | `int \| None`  | `config["power_plan"].get("sleep_timeout_ac")`            | ⚠️ Opzionale            |
| `power_plan.cpu_min_percent`      | `int`          | `config["power_plan"].get("cpu_min_percent", 5)`          | ⚠️ Opzionale            |
| `power_plan.cpu_max_percent`      | `int`          | `config["power_plan"].get("cpu_max_percent", 100)`        | ⚠️ Opzionale            |
| `extras`                          | `dict \| None` | `config.get("extras")`                                    | ⚠️ Nullable             |
| `extras.timezone`                 | `str`          | `config["extras"].get("timezone", "Europe/Rome")`         | ⚠️ Opzionale            |
| `extras.language`                 | `str`          | `config["extras"].get("language", "it-IT")`               | ⚠️ Opzionale            |
| `extras.keyboard_layout`          | `str`          | `config["extras"].get("keyboard_layout")`                 | ⚠️ Opzionale            |
| `extras.wallpaper_url`            | `str \| None`  | `config["extras"].get("wallpaper_url")`                   | ⚠️ Opzionale            |
| `extras.wifi`                     | `dict \| None` | `config["extras"].get("wifi")`                            | ⚠️ Nullable             |
| `extras.wifi.ssid`                | `str`          | `config["extras"]["wifi"]["ssid"]`                        | Se wifi presente        |
| `extras.wifi.password_encrypted`  | `str`          | `config["extras"]["wifi"]["password_encrypted"]`          | Se wifi presente        |
| `extras.windows_update`           | `str`          | `config["extras"].get("windows_update", "auto")`          | ⚠️ Opzionale            |

---

## Come decifrare `password_encrypted`

Le password cifrate (admin e WiFi) vengono decifrate **dal backend** prima di servire
il `wizard_config` all'agent tramite `/api/agent/auth`. L'agent riceve la password
**in chiaro** nella risposta JSON dell'autenticazione.

```python
# In AgentController.php (backend):
# config['admin_user']['password_encrypted'] viene decifrato con EncryptionService
# e restituito come config['admin_user']['password'] (in chiaro).
# L'agent NON deve mai implementare decifratura locale.

# Accesso corretto nell'agent:
password = wizard_config["admin_user"]["password"]  # in chiaro, dalla risposta /auth
```
