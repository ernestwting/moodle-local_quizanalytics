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
 * German language strings for local_quizanalytics.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'STACK q-type Analytics';
$string['quizanalytics:view'] = 'STACK-Testanalysen, -Modelle und -Diagnosen anzeigen';
$string['sectionselectorlabel'] = 'Abschnitt:';
$string['pagemaintitle'] = 'STACK q-type Analytics';
$string['sectionquiz'] = 'Test-Analyse';
$string['sectionquestion'] = 'Fragen-Analyse';
$string['sectionmodels'] = 'Modell-Analyse';
$string['sectiondiagnostics'] = 'Diagnose-Analyse';
$string['privacy:metadata'] = 'Das Plugin STACK q-type Analytics speichert selbst keine personenbezogenen Daten. Es liest abgeschlossene Testversuche, Fragenantworten, Bewertungen und Log-Ereignisse direkt aus Moodles eigener Datenbank (mod_quiz, der Frage-Engine, grade_grades und logstore_standard_log) zum Zeitpunkt der Anfrage bzw. Berechnung aus – all dies wird bereits durch deren eigene Datenschutz-Provider abgedeckt.';

// Abschnitt Test-Analyse.
$string['anonymizemode'] = 'Studierendendaten anonymisieren';
$string['anonymizedstudent'] = 'Studierende(r) {$a}';
$string['cachedef_questionanalysis'] = 'Das Ergebnis der Fragen-Analyse für einen Test.';
$string['cachedef_quizanalysiscoursewide'] = 'Das kursweite Ergebnis der Test-Analyse für einen Kurs.';
$string['cachedef_solutionprocess'] = 'Das Ergebnis der Lösungsprozess-Visualisierung für eine Auswahl aus Test/Frage/Teil/Studierende(r).';
$string['cachedef_solutionprocessmeta'] = 'Die Listen von Fragen/Teilen/Studierenden, die zum Befüllen des Auswahlformulars der Lösungsprozess-Visualisierung verwendet werden, für einen Test.';
$string['colorblindmode'] = 'Modus für Farbenblindheit';
$string['computetimelimit']      = 'Zeitlimit für Berechnungen (Sekunden)';
$string['computetimelimit_desc'] = 'Erhöht PHPs eigenes Zeitlimit für die Skriptausführung vor den aufwendigsten Analyseberechnungen (kursweite Analyse und jeglicher PDF-Export). Diese laufen im selben Prozess statt einen separaten Dienst aufzurufen, sodass ein Kurs mit vielen STACK-Tests/Studierenden mehr Zeit benötigen kann, als PHPs normales max_execution_time erlaubt. 0 belässt PHPs eigenen Standardwert.';
$string['coursewideheading']    = 'Kursweite Analytik';
$string['downloadpdfbutton']  = 'PDF herunterladen';
$string['generatepdfheading'] = 'PDF-Bericht erstellen';
$string['gobutton']             = 'Anzeigen';
$string['gradetypeaverage']     = 'Durchschnittsbewertung';
$string['gradetypehighest']     = 'Höchste Bewertung';
$string['gradetypelabel']       = 'Versuche vergleichen mit:';
$string['gradetypeminimum']     = 'Niedrigste Bewertung';
$string['loaderror']            = 'Die Analyse hat eine unerwartete Antwort geliefert.';
$string['noattempts']           = 'Für diesen Test liegen noch keine abgeschlossenen Versuche vor. Die Analyse erscheint, sobald mindestens ein(e) Studierende(r) ihn abgeschlossen hat.';
$string['nocourseattempts']     = 'Für keinen der STACK-Tests dieses Kurses liegen bisher abgeschlossene Versuche vor.';
$string['nostackquestions']     = 'Dieser Test enthält keine STACK-Fragen zum Visualisieren.';
$string['nostackquizzes']       = 'Dieser Kurs hat noch keine STACK-Tests, oder es liegen noch keine abgeschlossenen Versuche vor.';
$string['pagetitle']            = 'Testanalyse';
$string['pdfchartunavailable']  = '{$a}: Diagrammbild nicht verfügbar (nicht von der Seite erfasst).';
$string['pdfdownloadnotice']    = 'Die Erstellung dieses PDFs kann bei einem großen Kurs eine Weile dauern. Bitte warten Sie, bis der Download abgeschlossen ist.';
$string['pdferror']           = 'Der PDF-Bericht konnte nicht erstellt werden. Wenden Sie sich an Ihre Moodle-Administration.';
$string['pdfnosections']        = 'Für diesen Bericht wurden keine Abschnitte ausgewählt.';
$string['pdfquizsubtitle']        = 'Zusammengefasst über alle STACK-Tests des Kurses';
$string['pdfsectionattemptlist']       = '1. Zusammenfassung der Testversuche der Studierenden';
$string['pdfsectionboxplot']           = '3. Verteilung der Testbewertungen (Boxplot)';
$string['pdfsectioncrossattempt']      = 'Versuchsübergreifender Vergleich';
$string['pdfsectionengagement']        = '4. Beteiligung im Zeitverlauf';
$string['pdfsectionnetworkfeatures']   = 'Netzwerkmerkmale je Knoten';
$string['pdfsectionprtdistance3d']     = 'PRT-Distanz-3D-Diagramm';
$string['pdfsectionquestiondetails']        = 'Fragenüberprüfung';
$string['pdfsectionquestiondetailscaption'] = 'Prüfen Sie die Frage, die erwartete Antwort und häufige Antwortmuster, um zu verstehen, wo Studierende Schwierigkeiten hatten.';
$string['pdfsectionquizstats']         = '2. Zusammenfassung der Test-Statistik';
$string['pdfsectionresponseoverview'] = 'Übersicht der Fragenantworten';
$string['pdfsectionscatter']           = '5. Streudiagramm: Versuche vs. Bewertungen';
$string['pdfsectionsummary']        = 'Test-Momentaufnahme';
$string['pdfsectionsummarycaption'] = 'Teilnahme- und zusammenfassende Statistiken';
$string['pdfsectiontransitiongraph']   = 'Klassenweiter Übergangsgraph';
$string['pdfsectiontreeeditdistance3d'] = 'Baum-Editierdistanz-3D-Diagramm';
$string['pdfsectiontrend']             = '6. Liniendiagramm verschiedener Kennzahlen';
$string['pdfsolutionprocesssubtitle'] = '{$a->question}, Teil {$a->part}';
$string['pdftitlequestion']       = '{$a}: Fragen-Analyse';
$string['pdftitlequiz']           = '{$a}: Test-Analyse';
$string['pdftitlesolutionprocess'] = '{$a}: Lösungsprozess-Visualisierung';
$string['pdftruncatedrows']       = 'Zeigt die ersten {$a->shown} von {$a->total} Zeilen.';
$string['quizselectoption']     = 'Alle STACK-Tests (kursweite Ansicht)';
$string['selectpart']           = 'Teil';
$string['selectquestion']       = 'Frage';
$string['selectstudent']        = 'Studierenden-Drilldown';
$string['selectstudentnone']    = 'Keine';
$string['servererror']          = 'Die Analyse für diesen Test konnte nicht berechnet werden. Wenden Sie sich an Ihre Moodle-Administration.';
$string['viewquestionanalytics'] = 'Fragen-Analyse';
$string['viewsolutionprocess']  = 'Lösungsprozess-Visualisierung';

// Abschnitte Modell-Analyse und Diagnose-Analyse.
$string['indicator:gradetrajectory'] = 'STACK-Bewertungsverlauf';
$string['indicator:responselatencyanomaly'] = 'Anomale STACK-Antwortlatenz';
$string['indicator:disengagemententropy'] = 'STACK-Entkopplungsentropie';
$string['indicator:helpseekinggap'] = 'STACK-Hilfesuche-Lücke';
$string['indicator:feedbackrevisiondistance'] = 'STACK-Feedback-Revisionsdistanz';
$string['target:studentatrisk'] = 'Studierende(r) gefährdet in einem STACK-basierten Kurs';
$string['errornostackactivity'] = 'Dieser Kurs hat keine STACK-Fragenaktivität (qtype_stack)';
$string['indicator:questiondifficultyirt'] = 'STACK-Fragenschwierigkeit';
$string['indicator:syntaxerrorrate'] = 'STACK-Syntaxfehlerrate';
$string['indicator:unreachednoderatio'] = 'STACK-PRT-Anteil nicht erreichter Knoten';
$string['indicator:feedbackineffectiveness'] = 'STACK-Feedback-Unwirksamkeit';
$string['target:questionneedsreview'] = 'STACK-Frage/PRT muss überprüft werden';
$string['dashboardtitle'] = 'STACK q-type Analytics Dashboard';
$string['courseselectorlabel'] = 'Kurs:';
$string['quizselectorlabel'] = 'Test:';
$string['viewselectorlabel'] = 'Ansicht:';
$string['allquizzes'] = 'Alle Tests';
$string['largecoursenotice'] = 'Dies kann bei einem großen Kurs etwas Zeit zum Laden benötigen. Bitte warten Sie auf die Ergebnisse unten.';
$string['seedbiasheading'] = 'Seed-Verzerrung (einfaktorielle ANOVA über zufällige Seeds)';
$string['bloatedtreeheading'] = 'PRT-Zweigabdeckung';
$string['seedgroups'] = 'Beobachtete unterschiedliche Seeds';
$string['notenoughdata'] = 'Noch nicht genügend Versuchsdaten vorhanden, um dies zu berechnen.';
$string['noattemptsyet'] = 'Noch keine Versuche erfasst.';
$string['notenoughdatacount'] = 'Noch nicht genügend Versuchsdaten vorhanden, um dies zu berechnen ({$a} Versuch(e) bisher).';
$string['notavailable'] = 'k. A.';
$string['etamagnitude_negligible'] = 'vernachlässigbarer Effekt';
$string['etamagnitude_small'] = 'kleiner Effekt';
$string['etamagnitude_medium'] = 'mittlerer Effekt';
$string['etamagnitude_large'] = 'großer Effekt';
$string['node'] = 'Knoten';
$string['branch'] = 'Zweig';
$string['traversals'] = 'Beobachtete Durchläufe';
$string['coverage'] = 'Abdeckung';
$string['coverage_unreached'] = 'Nie erreicht: Kandidat zum Entfernen';
$string['coverage_low_traffic'] = 'Geringe Nutzung: vor dem Entfernen prüfen';
$string['coverage_adequate'] = 'Ausreichend durchlaufen';
$string['unknownquestion'] = 'Unbekannte Frage';
$string['unknownquiz'] = 'Unbekannter Test';
$string['model1heading'] = 'Modell 1: Risiko und Verhalten der Studierenden';
$string['model1intro'] = 'Sagt vorher, welche Studierenden Gefahr laufen, den Kurs nicht zu bestehen, anhand von fünf Verhaltenssignalen aus ihrer STACK-Fragenaktivität. Es wird an mehreren Punkten im Kursverlauf neu berechnet, sodass eine Warnung schon vor Kursende ausgelöst werden kann und nicht erst bei der Abschlussbewertung.';
$string['aboutthismodel'] = 'Über dieses Modell';
$string['model1aboutbody'] = 'Was tatsächlich vorhergesagt wird (die „Zielgröße") ist einfach: Wird die Abschlussbewertung dieser/dieses Studierenden unter die Bestehensgrenze des Kurses fallen? Die fünf unten aufgeführten Indikatoren sind das, was ein trainiertes Modell als Belege für diese Vorhersage nutzen würde. Da derzeit noch kein Modell trainiert ist, zeigt diese Seite lediglich den aktuellen Stand jedes Indikators direkt an.';
$string['model1aboutfooter'] = 'Dieses Modell wird deaktiviert ausgeliefert, daher handelt es sich hier noch um keine trainierte KI-Vorhersage. Es werden nur Live-Messwerte der einzelnen Signale angezeigt. Eine Administration kann es unter Website-Administration > Analytics > Modelle aktivieren und trainieren; danach erscheinen trainierte Vorhersagen in Moodles eigenem Insights-Bericht.';
$string['model1nostudents'] = 'In diesem Kurs sind noch keine Studierenden eingeschrieben.';
$string['columnstudent'] = 'Studierende(r)';
$string['columncurrentstatus'] = 'Aktueller Status';
$string['gradestatusatrisk'] = 'Gefährdet: {$a->grade}%, unter den {$a->gradepass}%, die zum Bestehen benötigt werden';
$string['gradestatuspassing'] = 'Auf Kurs: {$a->grade}%, auf oder über den {$a->gradepass}%, die zum Bestehen benötigt werden';
$string['gradestatusnogradeyet'] = 'Noch keine Bewertung erfasst';
$string['gradestatusnothreshold'] = 'Für diesen Kurs ist keine Bestehensgrenze festgelegt';
$string['band_good'] = 'Gut';
$string['band_neutral'] = 'Typisch';
$string['band_watch'] = 'Einen Blick wert';
$string['truncatednotice'] = 'Zeigt die ersten {$a->shown} von {$a->total}. Nutzen Sie die obigen Auswahlfelder, um dies einzugrenzen.';
$string['model1desc_gradetrajectory'] = 'Wie die STACK-Bewertungen dieser/dieses Studierenden im Vergleich zur vollen Punktzahl ausfallen.';
$string['model1sentence_gradetrajectory'] = 'Durchschnittlich {$a->meanpercent}% über {$a->attempts} abgeschlossene(n) Versuch(e).';
$string['model1desc_responselatencyanomaly'] = 'Ob diese(r) Studierende im Vergleich zur Klasse unplausibel schnell antwortet. Dies ist lediglich ein korrelatives Signal, kein Beleg für Fehlverhalten für sich genommen.';
$string['model1sentence_responselatencyanomaly'] = 'Durchschnittlich {$a->userseconds}s zwischen Versuchen, gegenüber einem Klassendurchschnitt von {$a->cohortseconds}s.';
$string['model1desc_disengagemententropy'] = 'Ob die Versuche dieser/dieses Studierenden mechanisch wirken (sehr regelmäßiges Timing, abgebrochene Fragen) statt echter Problemlösung.';
$string['model1sentence_disengagemententropy'] = '{$a->abandonedcount} von {$a->attempts} Versuch(en) vor Abschluss abgebrochen.';
$string['model1desc_helpseekinggap'] = 'Ob diese(r) Studierende nach einer falschen Antwort genauso häufig Hilfe sucht (Foren, Glossar, andere Ressourcen) wie ihre/seine Mitstudierenden.';
$string['model1sentence_helpseekinggap'] = 'Sucht nach {$a->studentpercent}% der Fehler Hilfe, gegenüber einem Klassendurchschnitt von {$a->baselinepercent}%.';
$string['model1desc_feedbackrevisiondistance'] = 'Ob diese(r) Studierende ihre/seine Antwort nach dem Ansehen des Feedbacks inhaltlich ändert, oder etwas fast Identisches erneut einreicht.';
$string['model1sentence_feedbackrevisiondistance'] = 'Ändert die Antwort im Schnitt um {$a->changepercent}%, über {$a->revisions} Überarbeitung(en).';
$string['model2heading'] = 'Modell 2: Qualität von Fragen und PRTs';
$string['model2intro'] = 'Eine Zeile pro STACK-Frage (mit dem zugehörigen Test darunter angezeigt), die anhand von vier Signalen kennzeichnet, wie Studierende die Fragen tatsächlich beantworten – einschließlich ihres PRT, der schrittweisen Bewertungslogik, die die Antwort prüft und Feedback gibt –, welche eine Überprüfung durch die Lehrperson lohnen könnten.';
$string['model2aboutbody'] = 'Was tatsächlich vorhergesagt wird (die „Zielgröße") ist: Fällt die Erfolgsquote dieser Frage unter einen Schwellenwert (standardmäßig 50%, eine Administrationseinstellung)? Die vier unten aufgeführten Indikatoren sind die Belege, die ein trainiertes Modell dafür nutzen würde. Da derzeit noch kein Modell trainiert ist, zeigt diese Seite lediglich den aktuellen Stand jedes Indikators direkt an. Hinweis: Dieser Erfolgsquoten-Messwert und der Schwierigkeitsindikator stammen letztlich beide aus derselben Erfolgsquote; behandeln Sie „muss überprüft werden" und „schwierig" daher als verwandte, nicht unabhängige Signale.';
$string['model2noquestions'] = 'Für diese Auswahl sind keine STACK-Fragen anzuzeigen.';
$string['columnquestion'] = 'Frage';
$string['quizlabel'] = 'Test: {$a}';
$string['quizoptionlabel'] = '{$a->name} ({$a->count} STACK-Frage(n))';
$string['needsreviewyes'] = 'Muss überprüft werden: {$a->passpercent}% Erfolgsquote, unter dem Schwellenwert von {$a->thresholdpercent}%';
$string['needsreviewno'] = 'Keine Kennzeichnung: {$a->passpercent}% Erfolgsquote, auf oder über dem Schwellenwert von {$a->thresholdpercent}%';
$string['model2desc_questiondifficultyirt'] = 'Wie schwer diese Frage in der Praxis ist, anhand ihrer empirischen Erfolgsquote.';
$string['model2sentence_questiondifficultyirt'] = '{$a->passpercent}% Erfolgsquote über {$a->attempts} abgeschlossene(n) Versuch(e).';
$string['model2desc_syntaxerrorrate'] = 'Ob die meisten falschen Antworten dieser Frage Eingabe-/Syntaxfehler sind (ein Problem des Eingabeformats) statt echter mathematischer Fehler.';
$string['model2sentence_syntaxerrorrate'] = '{$a->syntaxerrorcount} von {$a->totalfailed} fehlgeschlagenen Versuch(en) waren Syntax-/Eingabefehler.';
$string['model2desc_unreachednoderatio'] = 'Wie viel von der PRT-Verzweigungslogik dieser Frage noch nie durch einen echten Versuch durchlaufen wurde – ein Kandidat zum Entfernen, sollte sich das nicht ändern.';
$string['model2sentence_unreachednoderatio'] = '{$a->unreachedcount} von {$a->totalbranches} PRT-Zweig(en) nie erreicht.';
$string['model2desc_feedbackineffectiveness'] = 'Ob Studierende, die diese Frage falsch beantworten, sich beim nächsten Versuch stärker verbessern als bei einer neuen Frage – ein grober Hinweis darauf, ob das Feedback tatsächlich hilft.';
$string['model2sentence_feedbackineffectiveness'] = '{$a->improvepercent}% verbessern sich nach einem falschen Versuch, gegenüber einer Erstversuchs-Basislinie von {$a->baselinepercent}%.';
$string['diagnosticsheading'] = 'Diagnose-Dashboard';
$string['diagnosticsintrosummary'] = 'Was Seed-Verzerrung und PRT-Zweigabdeckung bedeuten';
$string['diagnosticsintro'] = 'Zwei Prüfungen pro STACK-Frage, unten aufgelistet mit dem zugehörigen Test. Jedes Mal, wenn ein(e) Studierende(r) eine STACK-Frage versucht, wählt Moodle einen zufälligen „seed", der ihre Zahlen ändert (z. B. andere Koeffizienten), während die Struktur gleich bleibt. <strong>Seed-Verzerrung</strong> prüft, ob manche dieser Seed-Varianten unfair schwerer oder leichter sind als andere, sodass eine niedrige Bewertung nicht einfach bedeutet „du hattest die schwerere Version". Jede STACK-Frage bewertet Antworten außerdem über ein PRT (ihre schrittweise Bewertungs-/Feedback-Logik, bestehend aus „Zweigen" für verschiedene richtige/falsche Pfade). <strong>PRT-Zweigabdeckung</strong> prüft, ob manche dieser Zweige jemals tatsächlich durch eine echte Antwort eines/einer Studierenden ausgelöst wurden. Ein nie erreichter Zweig ist entweder funktionierendes Feedback, das bisher niemand gebraucht hat, oder tote Logik, die es zu vereinfachen lohnt. Ein Abzeichen „Einen Blick wert" ist eine Aufforderung, diese Frage zu öffnen und zu prüfen, ob sie zu Ihrer ursprünglichen Gestaltung passt – kein Beweis dafür, dass etwas kaputt ist. Klicken Sie unten auf eine Frage, um die vollständigen Zahlen hinter ihren Abzeichen zu sehen.';
$string['conceptdependencynote'] = 'Das Mapping von Konzeptabhängigkeiten (die Ermittlung, welche fehlgeschlagenen Fragen tendenziell Fehlschläge bei anderen vorhersagen) ist in diesem Plugin noch nicht implementiert. Das Architekturdokument beschreibt dies als Offline-Sequenzanalyse außerhalb einer Live-Dashboard-Seite, nicht als etwas, das hier nur halb umgesetzt werden sollte. Dies wird vermerkt, damit es nicht einfach stillschweigend fehlt.';
$string['diagnosticsnoquestions'] = 'Für diese Auswahl sind keine STACK-Fragen anzuzeigen.';
$string['diagnosticsseedbiassentence'] = 'η²={$a->etasquared} ({$a->magnitude})';
$string['diagnosticsbloatedtreesentence'] = '{$a->unreached} von {$a->total} Zweig(en) nie erreicht';
$string['modelpageintro'] = '<strong>Modell 1</strong> betrachtet das Verhalten jeder/jedes Studierenden und kennzeichnet, wer möglicherweise Gefahr läuft, nicht zu bestehen. <strong>Modell 2</strong> betrachtet die Bewertungslogik jeder Frage und kennzeichnet solche, die eine Überprüfung durch die Lehrperson lohnen könnten. Verwenden Sie unten die Auswahl „Ansicht:", um zwischen beiden zu wechseln. Beide Modelle sind standardmäßig deaktiviert, daher ist alles unten ein Live-Messwert des jeweiligen Signals von heute, keine trainierte KI-Vorhersage. Eine Administration kann ein Modell unter Website-Administration, Analytics, Modelle aktivieren und trainieren. Nach dem Training erscheinen echte Vorhersagen zusätzlich zu dieser Seite in Moodles eigenem Insights-Bericht.';
$string['modelpageintrosummary'] = 'Über Modell 1 und Modell 2';
$string['diagnosticspageintro'] = 'Das Diagnose-Dashboard ist eine Reihe statistischer Berichte, die zu keinem der beiden Modelle passen: Seed-Verzerrung und PRT-Zweigabdeckung, unten beschrieben. Dies sind keine Vorhersagen, sondern direkte Berechnungen aus denselben Versuchsdaten.';
$string['diagnosticspageintrosummary'] = 'Über das Diagnose-Dashboard';
$string['responsibleusecallout'] = 'Beim Lesen der unten aufgeführten Kennzeichnungen sollten Sie ein paar Dinge im Hinterkopf behalten. Dies sind statistische Muster, kein Beweis für irgendetwas. Eine anomale Antwortzeit ist eine Aufforderung, mit einer/einem Studierenden das Gespräch zu suchen, kein Beleg für Fehlverhalten für sich genommen. Kleine Kurse zeigen allein deshalb verrauschtere und weniger zuverlässige Messwerte, weil ihnen weniger Datenpunkte zur Verfügung stehen. Jede Zahl hier beschreibt, was eine/ein Studierende(r) in diesem Kurs getan hat, nicht, wer sie/er ist.';
$string['responsibleusesummary'] = 'Verantwortungsvoller Umgang: einige Dinge, die zu beachten sind';
$string['pdfsectionslabel'] = 'In das PDF einschließen:';
$string['pdfnorows'] = 'Für diesen Abschnitt gibt es nichts anzuzeigen: noch keine Daten, oder nichts entsprach den aktuellen Filtern.';
$string['pdffooternote'] = 'STACK q-type Analytics Dashboard: Live-Indikatormesswerte, keine trainierte KI-Vorhersage';
$string['questionneedsreviewthreshold'] = 'Schwellenwert für „Frage muss überprüft werden" (Erfolgsquote)';
$string['questionneedsreviewthreshold_desc'] = 'Eine Frage wird als „muss überprüft werden" gekennzeichnet (das Proxy-Label von Modell 2), wenn ihre empirische Erfolgsquote unter diesen Wert (0,0-1,0) fällt. Beachten Sie den Zirkularitätshinweis in §3.3 des Architekturdokuments, bevor Sie diesen Wert absenken, um ein bestimmtes Ergebnis zu erzwingen.';
$string['task:warmanalyticscache'] = 'Ergebnis-Caches von STACK q-type Analytics aufwärmen';
$string['task:warmsingleview'] = 'Eine Test-/Fragen-Analyse-Ansicht aufwärmen (bedarfsgesteuerte Hintergrundberechnung)';
$string['generatinginbackground'] = 'Dies kann bei einem großen Kurs etwas Zeit zum Laden benötigen. Die Berechnung erfolgt im Hintergrund. Diese Seite prüft automatisch alle 20 Sekunden erneut. Sie müssen sie nicht selbst neu laden.';
$string['generatingstale'] = 'Dies wartet nun seit {$a} in der Warteschlange, ohne abgeschlossen zu sein. Das ist länger, als jede echte Hintergrundberechnung auf dieser Website bisher gedauert hat; höchstwahrscheinlich läuft Moodles Cron nicht, oder die Hintergrundaufgabe ist abgestürzt oder hat den Speicher überschritten. Bitten Sie Ihre Moodle-Administration, unter Website-Administration → Server → Geplante Aufgaben (läuft Cron überhaupt?) und Website-Administration → Server → Aufgaben → Aufgabenprotokolle (ist diese Aufgabe fehlgeschlagen?) nachzusehen, statt länger zu warten.';
$string['cronstatusheading'] = 'Cron-Status';
$string['cronstatuswarning'] = 'Dieses Plugin setzt voraus, dass Moodles Cron regelmäßig läuft.';
$string['detectedresourcesheading'] = 'Erkannte Serverressourcen';
$string['detectedresources'] = 'Auf diesem Server wurden {$a->cores} CPU-Kerne und {$a->memorygb} GB RAM erkannt. Empfohlene parallele Worker für die Cache-Aufwärmung: {$a->workers}. Wird bei Installation/Aktualisierung automatisch angewendet. Klicken Sie unten auf „Neu erkennen", falls sich die Hardware dieses Servers seither geändert hat (z. B. Umzug von einem Laptop auf einen dedizierten Server oder umgekehrt).';
$string['detectedresourcesfailed'] = 'CPU/RAM dieses Servers konnten nicht erkannt werden (ungewöhnlicher Host oder eingeschränkter Container). Stattdessen werden die ursprünglichen konservativen Standardwerte dieses Plugins verwendet. Die Einstellung „Parallele Worker für die Cache-Aufwärmung" unten kann weiterhin manuell festgelegt werden.';
$string['redetectbutton'] = 'Jetzt neu erkennen und anwenden';
$string['backgroundtimebudget'] = 'Zeitbudget für bedarfsgesteuerte Hintergrundberechnung (Sekunden)';
$string['backgroundtimebudget_desc'] = 'Wenn ein Besuchender auf einen kalten Cache trifft (Test-Analyse, Fragen-Analyse oder Lösungsprozess-Visualisierung), misst dieses Plugin zunächst die Zeit für eine echte, kleine Stichprobenabfrage (~100 Versuche) für den tatsächlichen Test auf diesem tatsächlichen Host und extrapoliert daraus die Gesamtkosten. Dies ist keine feste Schätzung anhand der Versuchsanzahl, da die Kosten pro Versuch tatsächlich von der Fragenkomplexität und der Hostgeschwindigkeit abhängen (direkt gemessen: eine einfache randomisierte Frage wurde pro Versuch etwa 10-mal günstiger berechnet als eine komplexe reale Frage). Übersteigt die Schätzung diese Anzahl Sekunden, wird die Berechnung an eine Hintergrundaufgabe übergeben, statt bei dieser Anfrage ausgeführt zu werden. Der synchrone bedarfsgesteuerte Pfad forkt nicht und kennt keine Obergrenze für Test-/Kursgröße, sodass ein hinreichend großer Test das eigene Timeout eines Reverse-Proxys überdauern kann (Cloudflares kostenloser/Pro-Standard liegt bei ~100s), bevor der eigene ignore_user_abort(true)-Schutz dieses Plugins überhaupt zum Tragen käme. Der/Die Besuchende sieht einen Hinweis „wird im Hintergrund erstellt" und muss die Seite erneut aufrufen, sobald sie fertig ist, statt auf dieselbe Anfrage zu warten. Auf 0 setzen, um dies zu deaktivieren und immer synchron zu berechnen (das alte Verhalten). Der Standardwert lässt echten Spielraum unter einem 100s-Proxy-Timeout, selbst nach Hinzurechnen des Analyseschritts und der eigenen konservativen Verzerrung der Stichprobenschätzung.';
$string['parallelworkers'] = 'Parallele Worker für die Cache-Aufwärmung';
$string['parallelworkers_desc'] = 'Die geplante Aufgabe „Ergebnis-Caches von STACK q-type Analytics aufwärmen" forkt bis zu so viele Worker-Prozesse, um die Versuchsdaten mehrerer Tests gleichzeitig abzurufen (nur CLI/Cron. Dies hat keine Auswirkung auf die bedarfsgesteuerten Webseiten, die immer seriell abrufen). Die Antwortzusammenfassung jeder STACK-Frage wird bei jedem Abruf live über CAS bewertet, was I/O-gebunden ist und auf das Maxima-Backend wartet; daher kann das gleichzeitige Abrufen mehrerer Tests die Aufwärmzeit eines großen Kurses spürbar verkürzen. 1 deaktiviert das Forken (fällt auf den ursprünglichen seriellen Abruf zurück). Halten Sie diesen Wert bei oder unter der eigenen Worker-/Warteschlangenkapazität Ihres Maxima-Backends und den verfügbaren Verbindungen Ihrer Datenbank. Jeder Worker öffnet eine eigene Datenbankverbindung und führt CAS-Aufrufe parallel zu den anderen aus. Siehe auch „Speicherlimit für Cache-Aufwärmungs-Worker" unten. Die beiden Einstellungen müssen gemeinsam auf den tatsächlich verfügbaren RAM Ihres Servers abgestimmt werden.';
$string['parallelworkermemory'] = 'Speicherlimit für Cache-Aufwärmungs-Worker (MB)';
$string['parallelworkermemory_desc'] = 'Das PHP-Speicherlimit (in MB), das jedem Cache-Aufwärmungs-Worker-Prozess zugestanden wird, einschließlich des nicht geforkten Falls (ein einzelner Abruf verwendet ebenfalls dieses Limit). Der Abruf jedes Workers streamt die Datensätze eines Tests jeweils auf die Festplatte, statt seinen gesamten Anteil im Speicher zu sammeln, doch die Versuchsdaten eines einzelnen, sehr großen Tests müssen für sich allein in den Speicher passen. Direkt gemessen erreichte ein einzelner Test mit 2.136 Versuchen einen Spitzenwert von knapp unter 1 GB, weshalb der Standardwert hier höher liegt. Stimmen Sie diesen Wert gemeinsam mit „Parallele Worker für die Cache-Aufwärmung" ab: Wenn so viele Worker gleichzeitig laufen können, stellen Sie sicher, dass Worker × dieser Wert komfortabel unter den tatsächlich verfügbaren RAM Ihres Servers passt, mit Raum für MariaDB/Postgres, das Maxima-Backend und alles andere, was bereits läuft. Eine Überschreitung lässt nicht nur diesen einen Kurs sauber fehlschlagen, sondern riskiert, dass der Out-of-Memory-Killer des Kernels stattdessen einen unbeteiligten Prozess (die Datenbank, Maxima) beendet, was ein weit größeres Problem darstellt. Senken Sie im Zweifel lieber parallelworkers, statt diesen Wert über das hinaus zu erhöhen, was tatsächlich frei verfügbar ist.';
$string['lowtrafficfloor'] = 'Untergrenze für „geringe Nutzung" bei aufgeblähten Bäumen';
$string['lowtrafficfloor_desc'] = 'Im Diagnose-Dashboard wird ein PRT-Zweig mit mindestens einem, aber weniger als dieser Anzahl beobachteter Durchläufe als „geringe Nutzung" gemeldet (erfordert eine manuelle Prüfung) statt als „nie erreicht" (Kandidat zum Entfernen).';
$string['helpseekinglookback'] = 'Rückblickfenster für Hilfesuche (Sekunden)';
$string['helpseekinglookback_desc'] = 'Wie lange nach einer fehlgeschlagenen STACK-Frage ein Zugriff auf Forum/Glossar/Ressource noch als „Hilfesuche dafür" zählt, für den Hilfesuche-Lücke-Indikator. Standardmäßig eine Stunde.';
