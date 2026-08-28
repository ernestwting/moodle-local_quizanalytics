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
 * Italian language strings for local_quizanalytics.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'STACK q-type Analytics';
$string['quizanalytics:view'] = 'Visualizza le analisi dei quiz STACK, i modelli e le diagnostiche';
$string['sectionselectorlabel'] = 'Sezione:';
$string['pagemaintitle'] = 'STACK q-type Analytics';
$string['sectionquiz'] = 'Analisi dei quiz';
$string['sectionquestion'] = 'Analisi delle domande';
$string['sectionmodels'] = 'Analisi dei modelli';
$string['sectiondiagnostics'] = 'Analisi diagnostiche';
$string['privacy:metadata'] = 'Il plugin STACK q-type Analytics non memorizza alcun dato personale proprio. Legge i tentativi di quiz completati, le risposte alle domande, i voti e gli eventi di log direttamente dal database di Moodle (mod_quiz, il motore delle domande, grade_grades e logstore_standard_log) al momento della richiesta/calcolo, tutti già coperti dai rispettivi provider della privacy.';

$string['anonymizemode'] = 'Anonimizza i dati degli studenti';
$string['anonymizedstudent'] = 'Studente {$a}';
$string['cachedef_questionanalysis'] = 'Il risultato dell\'Analisi delle domande per un quiz.';
$string['cachedef_quizanalysiscoursewide'] = 'Il risultato dell\'Analisi dei quiz a livello di corso per un corso.';
$string['cachedef_solutionprocess'] = 'Il risultato della Visualizzazione del processo di soluzione per una selezione di quiz/domanda/parte/studente.';
$string['cachedef_solutionprocessmeta'] = 'Gli elenchi di domande/parti/studenti usati per popolare il modulo di selezione della Visualizzazione del processo di soluzione, per un quiz.';
$string['colorblindmode'] = 'Modalità per daltonici';
$string['computetimelimit']      = 'Limite di tempo di calcolo (secondi)';
$string['computetimelimit_desc'] = 'Aumenta il limite di tempo di esecuzione di PHP prima dei calcoli di analisi più pesanti (analisi a livello di corso ed esportazione PDF). Questi calcoli vengono eseguiti in-process anziché tramite un servizio separato, quindi un corso con molti quiz/studenti STACK potrebbe richiedere più tempo di quanto consenta il normale max_execution_time di PHP. Il valore 0 lascia invariato il valore predefinito di PHP.';
$string['coursewideheading']    = 'Analisi a livello di corso';
$string['downloadpdfbutton']  = 'Scarica PDF';
$string['generatepdfheading'] = 'Genera report PDF';
$string['gobutton']             = 'Visualizza';
$string['gradetypeaverage']     = 'Voto medio';
$string['gradetypehighest']     = 'Voto più alto';
$string['gradetypelabel']       = 'Confronta i tentativi con:';
$string['gradetypeminimum']     = 'Voto minimo';
$string['loaderror']            = 'Le analisi hanno restituito una risposta inattesa.';
$string['noattempts']           = 'Nessun tentativo completato per questo quiz. Le analisi appariranno non appena almeno uno studente lo avrà completato.';
$string['nocourseattempts']     = 'Nessuno dei quiz STACK di questo corso ha ancora tentativi completati.';
$string['nostackquestions']     = 'Questo quiz non ha domande STACK da visualizzare.';
$string['nostackquizzes']       = 'Questo corso non ha ancora quiz STACK, oppure nessuno ha tentativi completati.';
$string['pagetitle']            = 'Analisi dei quiz';
$string['pdfchartunavailable']  = '{$a}: immagine del grafico non disponibile (non acquisita dalla pagina).';
$string['pdfdownloadnotice']    = 'La generazione di questo PDF può richiedere del tempo per un corso di grandi dimensioni. Attendere il completamento del download.';
$string['pdferror']           = 'Non è stato possibile generare il report PDF. Contattare l\'amministratore di Moodle.';
$string['pdfnosections']        = 'Nessuna sezione è stata selezionata per questo report.';
$string['pdfquizsubtitle']        = 'Combinato su tutti i quiz STACK del corso';
$string['pdfsectionattemptlist']       = '1. Riepilogo quiz per studente';
$string['pdfsectionboxplot']           = '3. Distribuzione dei voti del quiz (Box Plot)';
$string['pdfsectioncrossattempt']      = 'Confronto tra tentativi';
$string['pdfsectionengagement']        = '4. Coinvolgimento nel tempo';
$string['pdfsectionnetworkfeatures']   = 'Caratteristiche di rete per nodo';
$string['pdfsectionprtdistance3d']     = 'Grafico 3D Distanza PRT';
$string['pdfsectionquestiondetails']        = 'Revisione della domanda';
$string['pdfsectionquestiondetailscaption'] = 'Esamina la domanda, la risposta attesa e gli schemi di risposta più comuni per capire dove gli studenti hanno incontrato difficoltà.';
$string['pdfsectionquizstats']         = '2. Riepilogo delle statistiche del quiz';
$string['pdfsectionresponseoverview'] = 'Panoramica delle risposte alla domanda';
$string['pdfsectionscatter']           = '5. Grafico a dispersione: tentativi vs voti';
$string['pdfsectionsummary']        = 'Istantanea del quiz';
$string['pdfsectionsummarycaption'] = 'Statistiche di partecipazione e riepilogo';
$string['pdfsectiontransitiongraph']   = 'Grafico delle transizioni a livello di classe';
$string['pdfsectiontreeeditdistance3d'] = 'Grafico 3D Distanza di modifica dell\'albero';
$string['pdfsectiontrend']             = '6. Grafico a linee di varie metriche';
$string['pdfsolutionprocesssubtitle'] = '{$a->question}, parte {$a->part}';
$string['pdftitlequestion']       = '{$a}: Analisi delle domande';
$string['pdftitlequiz']           = '{$a}: Analisi del quiz';
$string['pdftitlesolutionprocess'] = '{$a}: Visualizzazione del processo di soluzione';
$string['pdftruncatedrows']       = 'Vengono mostrate le prime {$a->shown} righe su {$a->total}.';
$string['quizselectoption']     = 'Tutti i quiz STACK (vista a livello di corso)';
$string['selectpart']           = 'Parte';
$string['selectquestion']       = 'Domanda';
$string['selectstudent']        = 'Approfondimento per studente';
$string['selectstudentnone']    = 'Nessuno';
$string['servererror']          = 'Non è stato possibile calcolare le analisi per questo quiz. Contattare l\'amministratore di Moodle.';
$string['viewquestionanalytics'] = 'Analisi delle domande';
$string['viewsolutionprocess']  = 'Visualizzazione del processo di soluzione';

$string['indicator:gradetrajectory'] = 'Traiettoria del voto STACK';
$string['indicator:responselatencyanomaly'] = 'Latenza di risposta STACK anomala';
$string['indicator:disengagemententropy'] = 'Entropia di disimpegno STACK';
$string['indicator:helpseekinggap'] = 'Divario nella ricerca di aiuto STACK';
$string['indicator:feedbackrevisiondistance'] = 'Distanza di revisione del feedback STACK';
$string['target:studentatrisk'] = 'Studente a rischio in un corso basato su STACK';
$string['errornostackactivity'] = 'Questo corso non ha alcuna attività con domande STACK (qtype_stack)';
$string['indicator:questiondifficultyirt'] = 'Difficoltà della domanda STACK';
$string['indicator:syntaxerrorrate'] = 'Tasso di errori di sintassi STACK';
$string['indicator:unreachednoderatio'] = 'Rapporto di nodi PRT STACK mai raggiunti';
$string['indicator:feedbackineffectiveness'] = 'Inefficacia del feedback STACK';
$string['target:questionneedsreview'] = 'Domanda/PRT STACK da rivedere';
$string['dashboardtitle'] = 'STACK q-type Analytics Dashboard';
$string['courseselectorlabel'] = 'Corso:';
$string['quizselectorlabel'] = 'Quiz:';
$string['viewselectorlabel'] = 'Vista:';
$string['allquizzes'] = 'Tutti i quiz';
$string['largecoursenotice'] = 'Il caricamento può richiedere un po\' di tempo per un corso di grandi dimensioni. Attendere i risultati qui sotto.';
$string['seedbiasheading'] = 'Bias del seed (ANOVA a una via tra i seed casuali)';
$string['bloatedtreeheading'] = 'Copertura dei rami PRT';
$string['seedgroups'] = 'Seed distinti osservati';
$string['notenoughdata'] = 'Non ci sono ancora dati sufficienti sui tentativi per calcolarlo.';
$string['noattemptsyet'] = 'Nessun tentativo ancora registrato.';
$string['notenoughdatacount'] = 'Non ci sono ancora dati sufficienti sui tentativi per calcolarlo ({$a} tentativo/i finora).';
$string['notavailable'] = 'n/d';
$string['etamagnitude_negligible'] = 'effetto trascurabile';
$string['etamagnitude_small'] = 'effetto piccolo';
$string['etamagnitude_medium'] = 'effetto medio';
$string['etamagnitude_large'] = 'effetto grande';
$string['node'] = 'Nodo';
$string['branch'] = 'Ramo';
$string['traversals'] = 'Attraversamenti osservati';
$string['coverage'] = 'Copertura';
$string['coverage_unreached'] = 'Mai raggiunto: candidato alla rimozione';
$string['coverage_low_traffic'] = 'Traffico basso: da rivedere prima di rimuovere';
$string['coverage_adequate'] = 'Attraversato adeguatamente';
$string['unknownquestion'] = 'Domanda sconosciuta';
$string['unknownquiz'] = 'Quiz sconosciuto';
$string['model1heading'] = 'Modello 1: Rischio e comportamento dello studente';
$string['model1intro'] = 'Prevede quali studenti sono a rischio di non superare il corso, sulla base di cinque segnali comportamentali rilevati nella loro attività con le domande STACK. Viene ricalcolato in vari momenti del corso, in modo che un avviso possa scattare prima della fine del corso e non solo al voto finale.';
$string['aboutthismodel'] = 'Informazioni su questo modello';
$string['model1aboutbody'] = 'Ciò che viene effettivamente previsto (l\'"obiettivo") è semplice: il voto finale di questo studente scenderà sotto la soglia di sufficienza del corso? I cinque indicatori seguenti sono ciò che un modello addestrato userebbe come prova per questa previsione. Oggi, prima che qualsiasi modello venga addestrato, questa pagina mostra semplicemente la lettura attuale di ciascun indicatore.';
$string['model1aboutfooter'] = 'Questo modello viene distribuito disabilitato, quindi nulla di ciò che vedi qui è ancora una previsione di un\'IA addestrata. Vengono mostrate solo le letture in tempo reale di ciascun segnale. Un amministratore può abilitarlo e addestrarlo in Amministrazione del sito > Analytics > Modelli, dopodiché le previsioni addestrate compariranno nel report Insights di Moodle.';
$string['model1nostudents'] = 'Nessuno studente è ancora iscritto a questo corso.';
$string['columnstudent'] = 'Studente';
$string['columncurrentstatus'] = 'Stato attuale';
$string['gradestatusatrisk'] = 'A rischio: {$a->grade}%, sotto il {$a->gradepass}% richiesto per superare il corso';
$string['gradestatuspassing'] = 'In linea: {$a->grade}%, pari o superiore al {$a->gradepass}% richiesto per superare il corso';
$string['gradestatusnogradeyet'] = 'Nessun voto ancora registrato';
$string['gradestatusnothreshold'] = 'Questo corso non ha una soglia di sufficienza impostata';
$string['band_good'] = 'Buono';
$string['band_neutral'] = 'Nella norma';
$string['band_watch'] = 'Da tenere d\'occhio';
$string['truncatednotice'] = 'Vengono mostrati i primi {$a->shown} su {$a->total}. Usa i selettori sopra per restringere i risultati.';
$string['model1desc_gradetrajectory'] = 'Come si confrontano i punteggi STACK di questo studente rispetto al punteggio massimo.';
$string['model1sentence_gradetrajectory'] = 'Media del {$a->meanpercent}% su {$a->attempts} tentativo/i completato/i.';
$string['model1desc_responselatencyanomaly'] = 'Se questo studente risponde in modo implausibilmente rapido rispetto alla classe. Si tratta solo di un\'indicazione correlazionale, mai di per sé prova di scorrettezza.';
$string['model1sentence_responselatencyanomaly'] = 'Media di {$a->userseconds}s tra un tentativo e l\'altro, contro una media di classe di {$a->cohortseconds}s.';
$string['model1desc_disengagemententropy'] = 'Se i tentativi di questo studente appaiono meccanici (tempistiche molto regolari, domande abbandonate) anziché un genuino tentativo di risoluzione del problema.';
$string['model1sentence_disengagemententropy'] = '{$a->abandonedcount} tentativo/i su {$a->attempts} abbandonato/i prima del completamento.';
$string['model1desc_helpseekinggap'] = 'Se questo studente cerca aiuto (forum, glossario, altre risorse) dopo una risposta sbagliata tanto spesso quanto i suoi compagni di classe.';
$string['model1sentence_helpseekinggap'] = 'Cerca aiuto dopo il {$a->studentpercent}% degli errori, contro una media di classe del {$a->baselinepercent}%.';
$string['model1desc_feedbackrevisiondistance'] = 'Se questo studente modifica in modo significativo la propria risposta dopo aver visto il feedback, oppure la ripresenta quasi invariata.';
$string['model1sentence_feedbackrevisiondistance'] = 'Modifica la propria risposta in media del {$a->changepercent}%, su {$a->revisions} revisione/i.';
$string['model2heading'] = 'Modello 2: Qualità delle domande e dei PRT';
$string['model2intro'] = 'Una riga per ogni domanda STACK (con il quiz a cui appartiene indicato sotto), che segnala quelle che potrebbero valere la pena di una revisione da parte del docente, sulla base di quattro segnali derivati da come gli studenti rispondono effettivamente, incluso il loro PRT, la logica di valutazione passo-passo che controlla la risposta e fornisce il feedback.';
$string['model2aboutbody'] = 'Ciò che viene effettivamente previsto (l\'"obiettivo") è: il tasso di superamento di questa domanda scende sotto una soglia (50% per impostazione predefinita, un\'impostazione dell\'amministratore)? I quattro indicatori seguenti sono le prove che un modello addestrato userebbe per questo. Oggi, prima che qualsiasi modello venga addestrato, questa pagina mostra semplicemente la lettura attuale di ciascun indicatore. Nota: questa lettura del tasso di superamento e l\'indicatore di difficoltà derivano entrambi in ultima analisi dallo stesso tasso di superamento, quindi considera "da rivedere" e "difficile" come segnali correlati, non indipendenti.';
$string['model2noquestions'] = 'Nessuna domanda STACK da mostrare per questa selezione.';
$string['columnquestion'] = 'Domanda';
$string['quizlabel'] = 'Quiz: {$a}';
$string['quizoptionlabel'] = '{$a->name} ({$a->count} domanda/e STACK)';
$string['needsreviewyes'] = 'Da rivedere: tasso di superamento del {$a->passpercent}%, sotto la soglia del {$a->thresholdpercent}%';
$string['needsreviewno'] = 'Nessuna segnalazione: tasso di superamento del {$a->passpercent}%, pari o superiore alla soglia del {$a->thresholdpercent}%';
$string['model2desc_questiondifficultyirt'] = 'Quanto è difficile in pratica questa domanda, in base al suo tasso di superamento empirico.';
$string['model2sentence_questiondifficultyirt'] = 'Tasso di superamento del {$a->passpercent}% su {$a->attempts} tentativo/i completato/i.';
$string['model2desc_syntaxerrorrate'] = 'Se la maggior parte delle risposte sbagliate a questa domanda sono errori di digitazione/sintassi (un problema di formato dell\'input) piuttosto che veri errori matematici.';
$string['model2sentence_syntaxerrorrate'] = '{$a->syntaxerrorcount} tentativo/i falliti su {$a->totalfailed} erano errori di sintassi/digitazione.';
$string['model2desc_unreachednoderatio'] = 'Quanto della logica di ramificazione del PRT di questa domanda non è mai stata effettivamente attraversata da un tentativo reale, un candidato alla rimozione se la situazione rimane invariata.';
$string['model2sentence_unreachednoderatio'] = '{$a->unreachedcount} ramo/i PRT su {$a->totalbranches} mai raggiunto/i.';
$string['model2desc_feedbackineffectiveness'] = 'Se gli studenti che sbagliano questa domanda tendono a migliorare al tentativo successivo più di quanto farebbero con una domanda nuova, una stima approssimativa di quanto il feedback stia effettivamente aiutando.';
$string['model2sentence_feedbackineffectiveness'] = 'Il {$a->improvepercent}% migliora dopo un tentativo sbagliato, contro un valore di riferimento del {$a->baselinepercent}% al primo tentativo.';
$string['diagnosticsheading'] = 'Dashboard diagnostica';
$string['diagnosticsintrosummary'] = 'Cosa significano il Bias del seed e la Copertura dei rami PRT';
$string['diagnosticsintro'] = 'Due controlli per ogni domanda STACK, elencati di seguito insieme al quiz a cui appartengono. Ogni volta che uno studente tenta una domanda STACK, Moodle sceglie un "seed" casuale che ne cambia i numeri (ad es. coefficienti diversi) mantenendo la stessa struttura. Il <strong>bias del seed</strong> verifica se alcune di queste varianti del seed sono ingiustamente più difficili o più facili di altre, in modo che un voto basso non sia semplicemente "ti è capitata la versione più difficile". Ogni domanda STACK valuta inoltre le risposte tramite un PRT (la sua logica di valutazione/feedback passo-passo, composta da "rami" per i diversi percorsi giusti/sbagliati). La <strong>copertura dei rami PRT</strong> verifica se alcuni di questi rami sono mai stati effettivamente attivati da una risposta reale di uno studente. Un ramo mai raggiunto è o un feedback funzionante di cui nessuno ha ancora avuto bisogno, oppure logica morta che vale la pena semplificare. Un badge "Da tenere d\'occhio" è un invito ad aprire quella domanda e verificare che abbia senso rispetto a come è stata progettata, non una prova che qualcosa sia rotto. Clicca su una domanda qui sotto per vedere i numeri completi dietro i suoi badge.';
$string['conceptdependencynote'] = 'La mappatura delle dipendenze concettuali (individuare quali fallimenti in alcune domande tendano a prevedere fallimenti in altre) non è ancora implementata in questo plugin. Il documento di architettura la descrive come un lavoro di sequence-mining offline, al di fuori di una pagina dashboard in tempo reale, non qualcosa da implementare a metà qui. Segnalato qui affinché non manchi semplicemente in modo silenzioso.';
$string['diagnosticsnoquestions'] = 'Nessuna domanda STACK da mostrare per questa selezione.';
$string['diagnosticsseedbiassentence'] = 'η²={$a->etasquared} ({$a->magnitude})';
$string['diagnosticsbloatedtreesentence'] = '{$a->unreached} ramo/i su {$a->total} mai raggiunto/i';
$string['modelpageintro'] = 'Il <strong>Modello 1</strong> analizza il comportamento di ogni studente e segnala chi potrebbe essere a rischio di non superare il corso. Il <strong>Modello 2</strong> analizza la logica di valutazione di ogni domanda e segnala quelle che potrebbero valere la pena di una revisione da parte del docente. Usa il selettore "Vista:" qui sotto per passare dall\'uno all\'altro. Entrambi i modelli sono disabilitati per impostazione predefinita, quindi tutto ciò che vedi qui sotto è una lettura in tempo reale di ciascun segnale, non una previsione di un\'IA addestrata. Un amministratore può abilitare e addestrare un modello in Amministrazione del sito, Analytics, Modelli. Una volta addestrato, le previsioni reali compariranno accanto a questa pagina nel report Insights di Moodle.';
$string['modelpageintrosummary'] = 'Informazioni sul Modello 1 e sul Modello 2';
$string['diagnosticspageintro'] = 'La Dashboard diagnostica è un insieme di report statistici che non rientrano in nessuno dei due modelli: Bias del seed e Copertura dei rami PRT, descritti di seguito. Non si tratta di previsioni, ma solo di calcoli diretti a partire dagli stessi dati sui tentativi.';
$string['diagnosticspageintrosummary'] = 'Informazioni sulla Dashboard diagnostica';
$string['responsibleusecallout'] = 'Alcune cose da tenere presente leggendo le segnalazioni qui sotto. Si tratta di schemi statistici, non di prove di alcunché. Un tempo di risposta anomalo è un invito a parlare con uno studente, non di per sé una prova di scorrettezza. I corsi piccoli mostreranno letture più rumorose e meno affidabili semplicemente perché dispongono di meno dati su cui basarsi. Ogni numero qui descrive ciò che uno studente ha fatto in questo corso, non chi è.';
$string['responsibleusesummary'] = 'Uso responsabile: alcune cose da tenere presente';
$string['pdfsectionslabel'] = 'Includi nel PDF:';
$string['pdfnorows'] = 'Nulla da mostrare per questa sezione: nessun dato ancora disponibile, oppure nulla corrisponde ai filtri attuali.';
$string['pdffooternote'] = 'Dashboard STACK q-type Analytics: letture degli indicatori in tempo reale, non una previsione di un\'IA addestrata';
$string['questionneedsreviewthreshold'] = 'Soglia del tasso di superamento per "domanda da rivedere"';
$string['questionneedsreviewthreshold_desc'] = 'Una domanda viene etichettata come "da rivedere" (l\'etichetta proxy del Modello 2) quando il suo tasso di superamento empirico scende sotto questo valore (0,0-1,0). Consulta l\'avvertenza sulla circolarità al §3.3 del documento di architettura prima di abbassare questo valore per inseguire un risultato particolare.';
$string['task:warmanalyticscache'] = 'Precarica le cache dei risultati di STACK q-type Analytics';
$string['task:warmsingleview'] = 'Precarica una singola vista di Analisi dei quiz/domande (calcolo in background su richiesta)';
$string['generatinginbackground'] = 'Il caricamento può richiedere un po\' di tempo per un corso di grandi dimensioni. Il calcolo è in corso in background. Questa pagina si aggiorna automaticamente ogni 20 secondi. Non è necessario ricaricarla manualmente.';
$string['generatingstale'] = 'Questo elemento è in coda da {$a} senza essere completato. È più tempo di quanto abbia mai richiesto un calcolo in background reale su questo sito, quindi molto probabilmente significa che il cron di Moodle non è in esecuzione, oppure che l\'attività in background è andata in crash o ha esaurito la memoria. Chiedi al tuo amministratore di Moodle di controllare Amministrazione del sito → Server → Attività pianificate (il cron è effettivamente in esecuzione?) e Amministrazione del sito → Server → Attività → Log delle attività (questa attività è fallita?) invece di attendere ulteriormente.';
$string['cronstatusheading'] = 'Stato del cron';
$string['cronstatuswarning'] = 'Questo plugin dipende dall\'esecuzione regolare del cron di Moodle.';
$string['detectedresourcesheading'] = 'Risorse del server rilevate';
$string['detectedresources'] = 'Rilevati {$a->cores} core CPU e {$a->memorygb} GB di RAM su questo server. Worker paralleli consigliati per il precaricamento della cache: {$a->workers}. Applicato automaticamente all\'installazione/aggiornamento. Premi "Rileva di nuovo" qui sotto se l\'hardware di questo server è cambiato da allora (ad es. passaggio da un laptop a un server dedicato, o viceversa).';
$string['detectedresourcesfailed'] = 'Non è stato possibile rilevare la CPU/RAM di questo server (host insolito o container con restrizioni). Vengono invece utilizzati i valori predefiniti statici e prudenti originali di questo plugin. L\'impostazione "Worker paralleli per il precaricamento della cache" qui sotto può comunque essere impostata manualmente.';
$string['redetectbutton'] = 'Rileva di nuovo e applica ora';
$string['backgroundtimebudget'] = 'Budget di tempo per il calcolo in background su richiesta (secondi)';
$string['backgroundtimebudget_desc'] = 'Quando un visitatore incontra una cache non ancora popolata (Analisi dei quiz, Analisi delle domande o Visualizzazione del processo di soluzione), questo plugin cronometra prima un vero e proprio recupero di un piccolo campione (~100 tentativi) per il quiz effettivo su questo host effettivo, per poi estrapolarne il costo totale. Non si tratta di una stima basata su un numero fisso di tentativi, poiché il costo per tentativo dipende realmente dalla complessità della domanda e dalla velocità dell\'host (misurato direttamente: una semplice domanda randomizzata è risultata calcolata circa 10 volte più economicamente per tentativo rispetto a una domanda reale complessa). Se la stima supera questo numero di secondi, il calcolo viene affidato a un\'attività in background invece di essere eseguito nell\'ambito di quella richiesta. Il percorso sincrono su richiesta non prevede forking né alcun limite massimo alla dimensione del quiz/corso, quindi uno abbastanza grande può superare il timeout di un reverse proxy (il valore predefinito del piano gratuito/Pro di Cloudflare è di circa 100s) prima ancora che la protezione ignore_user_abort(true) di questo plugin abbia la possibilità di intervenire. Il visitatore vede un avviso "generazione in corso in background" e deve tornare sulla pagina una volta completata, invece di attendere nell\'ambito della stessa richiesta. Imposta 0 per disabilitare questa funzione e calcolare sempre in linea (il comportamento precedente). Il valore predefinito lascia un margine reale sotto un timeout del proxy di 100s, anche dopo aver aggiunto la fase di analisi e considerando la propensione prudente della stima basata sul campionamento.';
$string['parallelworkers'] = 'Worker paralleli per il precaricamento della cache';
$string['parallelworkers_desc'] = 'L\'attività pianificata "Precarica le cache dei risultati di STACK q-type Analytics" avvia fino a questo numero di processi worker per recuperare contemporaneamente i dati sui tentativi di più quiz (solo CLI/cron; questo non ha alcun effetto sulle pagine web su richiesta, che recuperano sempre i dati in modo seriale). Il riepilogo delle risposte di ogni domanda STACK viene valutato dal vivo tramite CAS ogni volta che viene recuperato, un\'operazione I/O-bound in attesa del backend Maxima, quindi eseguire contemporaneamente i recuperi di più quiz può ridurre significativamente il tempo di precaricamento di un corso di grandi dimensioni. Il valore 1 disabilita il forking (si torna al recupero seriale originale). Mantieni questo valore pari o inferiore alla capacità worker/coda del tuo backend Maxima e alle connessioni disponibili del tuo database. Ogni worker apre una propria connessione al database ed effettua chiamate CAS contemporaneamente agli altri. Vedi anche "Limite di memoria del worker per il precaricamento della cache" qui sotto. Le due impostazioni vanno dimensionate insieme in base alla RAM effettivamente disponibile sul tuo server.';
$string['parallelworkermemory'] = 'Limite di memoria del worker per il precaricamento della cache (MB)';
$string['parallelworkermemory_desc'] = 'Il limite di memoria PHP (in MB) consentito per ciascun processo worker di precaricamento della cache, incluso il caso non forkato (anche un singolo recupero usa questo limite). Il recupero di ogni worker trasmette in streaming su disco i record di un quiz alla volta, invece di accumulare in memoria l\'intera quota, ma i dati sui tentativi di un singolo quiz molto grande devono comunque stare interamente in memoria. Misurato direttamente, un singolo quiz con 2.136 tentativi ha raggiunto un picco appena inferiore a 1GB, motivo per cui il valore predefinito qui è più alto di quello. Dimensiona questo valore insieme a "Worker paralleli per il precaricamento della cache": se quel numero di worker può essere eseguito contemporaneamente, assicurati che worker × questo valore rientri comodamente nella RAM realmente disponibile sul tuo server, lasciando spazio per MariaDB/Postgres, il backend Maxima e tutto il resto già in esecuzione. Superare questo limite non fa semplicemente fallire in modo pulito questo singolo corso: rischia che l\'out-of-memory killer del kernel scelga invece un processo non correlato (il database, Maxima), il che è un problema molto più grande. In caso di dubbio, abbassa parallelworkers piuttosto che alzare questo valore oltre quanto sai essere effettivamente libero.';
$string['lowtrafficfloor'] = 'Soglia minima "traffico basso" per gli alberi gonfiati';
$string['lowtrafficfloor_desc'] = 'Nella Dashboard diagnostica, un ramo PRT con almeno un attraversamento osservato ma meno di questo numero viene segnalato come "traffico basso" (necessita di una verifica umana) anziché "mai raggiunto" (candidato alla rimozione).';
$string['helpseekinglookback'] = 'Finestra temporale di osservazione per la ricerca di aiuto (secondi)';
$string['helpseekinglookback_desc'] = 'Per quanto tempo dopo un fallimento in una domanda STACK l\'accesso a un forum/glossario/risorsa conta ancora come "ricerca di aiuto per essa", ai fini dell\'indicatore del divario nella ricerca di aiuto. Il valore predefinito è un\'ora.';
