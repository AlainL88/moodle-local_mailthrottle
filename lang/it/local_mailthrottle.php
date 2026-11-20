<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Italian language strings for local_mailthrottle.
 *
 * @package    local_mailthrottle
 * @copyright  2026 Alain Lima
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Limitazione invio email in uscita (Mail Throttle)';
$string['enabled'] = 'Abilita limitazione invio email';
$string['enabled_desc'] = 'Se abilitato, ogni singola email in uscita viene distanziata secondo l\'intervallo configurato per evitare blocchi anti-spam del server SMTP. Nota: finché un invio massivo non è completato, il relativo task cron rimane occupato e può ritardare l\'esecuzione degli altri task schedulati.';
$string['delayseconds'] = 'Intervallo tra email (secondi)';
$string['delayseconds_desc'] = 'Numero minimo di secondi tra due singole email consecutive (non tra connessioni SMTP; es. 12 = max 300/ora, 36 = max 100/ora; 0 = disabilitato). Con intervalli lunghi (es. > 30s) è consigliabile impostare "Limite sessione SMTP" (smtpmaxbulk) a 1 per evitare timeout di inattività della connessione SMTP.';
$string['clionly'] = 'Limita solo da CLI / cron';
$string['clionly_desc'] = 'Consigliato: applica il ritardo solo ai task cron CLI, evitando di rallentare le richieste web interattive (es. recupero password).';
$string['privacy:metadata'] = 'Il plugin Limitazione invio email in uscita non memorizza alcun dato personale.';
