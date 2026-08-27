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
 * Spanish language strings for local_quizanalytics.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'STACK q-type Analytics';
$string['quizanalytics:view'] = 'Ver análisis de cuestionarios STACK, modelos y diagnósticos';
$string['sectionselectorlabel'] = 'Sección:';
$string['pagemaintitle'] = 'STACK q-type Analytics';
$string['sectionquiz'] = 'Análisis de cuestionarios';
$string['sectionquestion'] = 'Análisis de preguntas';
$string['sectionmodels'] = 'Análisis de modelos';
$string['sectiondiagnostics'] = 'Análisis de diagnósticos';
$string['privacy:metadata'] = 'El plugin STACK q-type Analytics no almacena ningún dato personal propio. Lee los intentos de cuestionario finalizados, las respuestas a las preguntas, las calificaciones y los eventos de registro directamente desde la propia base de datos de Moodle (mod_quiz, el motor de preguntas, grade_grades y logstore_standard_log) en el momento de la solicitud o del cálculo, todo lo cual ya está cubierto por sus propios proveedores de privacidad.';

$string['anonymizemode'] = 'Anonimizar datos de estudiantes';
$string['anonymizedstudent'] = 'Estudiante {$a}';
$string['cachedef_questionanalysis'] = 'El resultado de Análisis de preguntas para un cuestionario.';
$string['cachedef_quizanalysiscoursewide'] = 'El resultado del Análisis de cuestionarios a nivel de curso para un curso.';
$string['cachedef_solutionprocess'] = 'El resultado de la Visualización del proceso de solución para una selección de cuestionario/pregunta/parte/estudiante.';
$string['cachedef_solutionprocessmeta'] = 'Las listas de preguntas/partes/estudiantes utilizadas para completar el formulario selector de la Visualización del proceso de solución, para un cuestionario.';
$string['colorblindmode'] = 'Modo para daltónicos';
$string['computetimelimit']      = 'Límite de tiempo de cálculo (segundos)';
$string['computetimelimit_desc'] = 'Aumenta el propio límite de tiempo de ejecución de PHP antes de los cálculos de análisis más pesados (el análisis a nivel de curso y cualquier exportación a PDF). Estos se ejecutan en el mismo proceso en lugar de llamar a un servicio independiente, por lo que un curso con muchos cuestionarios/estudiantes STACK puede necesitar más tiempo del que permite el max_execution_time normal de PHP. 0 deja el valor predeterminado de PHP sin cambios.';
$string['coursewideheading']    = 'Análisis a nivel de curso';
$string['downloadpdfbutton']  = 'Descargar PDF';
$string['generatepdfheading'] = 'Generar informe en PDF';
$string['gobutton']             = 'Ver';
$string['gradetypeaverage']     = 'Calificación promedio';
$string['gradetypehighest']     = 'Calificación más alta';
$string['gradetypelabel']       = 'Comparar intentos con:';
$string['gradetypeminimum']     = 'Calificación mínima';
$string['loaderror']            = 'El análisis devolvió una respuesta inesperada.';
$string['noattempts']           = 'Todavía no hay intentos finalizados para este cuestionario. El análisis aparecerá una vez que al menos un estudiante lo haya completado.';
$string['nocourseattempts']     = 'Ninguno de los cuestionarios STACK de este curso tiene intentos finalizados todavía.';
$string['nostackquestions']     = 'Este cuestionario no tiene preguntas STACK para visualizar.';
$string['nostackquizzes']       = 'Este curso todavía no tiene cuestionarios STACK, o ninguno tiene intentos finalizados.';
$string['pagetitle']            = 'Análisis de cuestionarios';
$string['pdfchartunavailable']  = '{$a}: imagen del gráfico no disponible (no se capturó desde la página).';
$string['pdfdownloadnotice']    = 'Generar este PDF puede tardar un tiempo en un curso grande. Espere a que finalice la descarga.';
$string['pdferror']           = 'No se pudo generar el informe en PDF. Póngase en contacto con su administrador de Moodle.';
$string['pdfnosections']        = 'No se seleccionó ninguna sección para este informe.';
$string['pdfquizsubtitle']        = 'Combinado entre todos los cuestionarios STACK del curso';
$string['pdfsectionattemptlist']       = '1. Resumen de cuestionarios por estudiante';
$string['pdfsectionboxplot']           = '3. Distribución de calificaciones del cuestionario (diagrama de caja)';
$string['pdfsectioncrossattempt']      = 'Comparación entre intentos';
$string['pdfsectionengagement']        = '4. Participación a lo largo del tiempo';
$string['pdfsectionnetworkfeatures']   = 'Características de red por nodo';
$string['pdfsectionprtdistance3d']     = 'Gráfico 3D de distancia PRT';
$string['pdfsectionquestiondetails']        = 'Revisión de preguntas';
$string['pdfsectionquestiondetailscaption'] = 'Examine la pregunta, la respuesta esperada y los patrones de respuesta comunes para entender dónde tuvieron dificultades los estudiantes.';
$string['pdfsectionquizstats']         = '2. Resumen de estadísticas del cuestionario';
$string['pdfsectionresponseoverview'] = 'Resumen de respuestas a la pregunta';
$string['pdfsectionscatter']           = '5. Diagrama de dispersión: intentos frente a calificaciones';
$string['pdfsectionsummary']        = 'Instantánea del cuestionario';
$string['pdfsectionsummarycaption'] = 'Estadísticas de participación y resumen';
$string['pdfsectiontransitiongraph']   = 'Gráfico de transición a nivel de clase';
$string['pdfsectiontreeeditdistance3d'] = 'Gráfico 3D de distancia de edición de árbol';
$string['pdfsectiontrend']             = '6. Gráfico de líneas de varias métricas';
$string['pdfsolutionprocesssubtitle'] = '{$a->question}, parte {$a->part}';
$string['pdftitlequestion']       = '{$a}: Análisis de preguntas';
$string['pdftitlequiz']           = '{$a}: Análisis de cuestionario';
$string['pdftitlesolutionprocess'] = '{$a}: Visualización del proceso de solución';
$string['pdftruncatedrows']       = 'Mostrando las primeras {$a->shown} de {$a->total} filas.';
$string['quizselectoption']     = 'Todos los cuestionarios STACK (vista a nivel de curso)';
$string['selectpart']           = 'Parte';
$string['selectquestion']       = 'Pregunta';
$string['selectstudent']        = 'Detalle por estudiante';
$string['selectstudentnone']    = 'Ninguno';
$string['servererror']          = 'No se pudo calcular el análisis para este cuestionario. Póngase en contacto con su administrador de Moodle.';
$string['viewquestionanalytics'] = 'Análisis de preguntas';
$string['viewsolutionprocess']  = 'Visualización del proceso de solución';

$string['indicator:gradetrajectory'] = 'Trayectoria de calificación STACK';
$string['indicator:responselatencyanomaly'] = 'Latencia de respuesta STACK anómala';
$string['indicator:disengagemententropy'] = 'Entropía de desconexión STACK';
$string['indicator:helpseekinggap'] = 'Brecha de búsqueda de ayuda STACK';
$string['indicator:feedbackrevisiondistance'] = 'Distancia de revisión de retroalimentación STACK';
$string['target:studentatrisk'] = 'Estudiante en riesgo en un curso basado en STACK';
$string['errornostackactivity'] = 'Este curso no tiene actividad de preguntas STACK (qtype_stack)';
$string['indicator:questiondifficultyirt'] = 'Dificultad de la pregunta STACK';
$string['indicator:syntaxerrorrate'] = 'Tasa de errores de sintaxis STACK';
$string['indicator:unreachednoderatio'] = 'Proporción de nodos PRT no alcanzados STACK';
$string['indicator:feedbackineffectiveness'] = 'Ineficacia de la retroalimentación STACK';
$string['target:questionneedsreview'] = 'La pregunta/PRT STACK necesita revisión';
$string['dashboardtitle'] = 'Dashboard de STACK q-type Analytics';
$string['courseselectorlabel'] = 'Curso:';
$string['quizselectorlabel'] = 'Cuestionario:';
$string['viewselectorlabel'] = 'Vista:';
$string['allquizzes'] = 'Todos los cuestionarios';
$string['largecoursenotice'] = 'Esto puede tardar un poco en cargar en un curso grande. Espere los resultados a continuación.';
$string['seedbiasheading'] = 'Sesgo de seed (ANOVA de un factor entre seeds aleatorios)';
$string['bloatedtreeheading'] = 'Cobertura de ramas PRT';
$string['seedgroups'] = 'Seeds distintos observados';
$string['notenoughdata'] = 'Todavía no hay suficientes datos de intentos para calcular esto.';
$string['noattemptsyet'] = 'Todavía no se han registrado intentos.';
$string['notenoughdatacount'] = 'Todavía no hay suficientes datos de intentos para calcular esto ({$a} intento(s) hasta ahora).';
$string['notavailable'] = 'n/d';
$string['etamagnitude_negligible'] = 'efecto insignificante';
$string['etamagnitude_small'] = 'efecto pequeño';
$string['etamagnitude_medium'] = 'efecto medio';
$string['etamagnitude_large'] = 'efecto grande';
$string['node'] = 'Nodo';
$string['branch'] = 'Rama';
$string['traversals'] = 'Recorridos observados';
$string['coverage'] = 'Cobertura';
$string['coverage_unreached'] = 'Nunca alcanzada: candidata a poda';
$string['coverage_low_traffic'] = 'Tráfico bajo: revisar antes de podar';
$string['coverage_adequate'] = 'Recorrida adecuadamente';
$string['unknownquestion'] = 'Pregunta desconocida';
$string['unknownquiz'] = 'Cuestionario desconocido';
$string['model1heading'] = 'Modelo 1: Riesgo y comportamiento del estudiante';
$string['model1intro'] = 'Predice qué estudiantes están en riesgo de no aprobar el curso, a partir de cinco señales de comportamiento en su actividad con preguntas STACK. Se vuelve a calcular en distintos momentos a lo largo del curso, de modo que una advertencia pueda activarse antes de que termine el curso, en lugar de solo con la calificación final.';
$string['aboutthismodel'] = 'Acerca de este modelo';
$string['model1aboutbody'] = 'Lo que realmente se predice (el "objetivo") es simple: ¿la calificación final de este estudiante quedará por debajo de la calificación de aprobación del curso? Los cinco indicadores siguientes son lo que un modelo entrenado usaría como evidencia para esa predicción. Hoy, antes de que se entrene ningún modelo, esta página simplemente muestra la lectura actual de cada indicador de forma directa.';
$string['model1aboutfooter'] = 'Este modelo se distribuye desactivado, por lo que nada de lo que aparece aquí es todavía una predicción de IA entrenada. Solo se muestran las lecturas en vivo de cada señal. Un administrador puede activarlo y entrenarlo en Administración del sitio > Analíticas > Modelos, tras lo cual las predicciones entrenadas aparecerán en el propio informe de Perspectivas (Insights) de Moodle.';
$string['model1nostudents'] = 'Todavía no hay estudiantes matriculados en este curso.';
$string['columnstudent'] = 'Estudiante';
$string['columncurrentstatus'] = 'Estado actual';
$string['gradestatusatrisk'] = 'En riesgo: {$a->grade}%, por debajo del {$a->gradepass}% necesario para aprobar';
$string['gradestatuspassing'] = 'En buen camino: {$a->grade}%, igual o por encima del {$a->gradepass}% necesario para aprobar';
$string['gradestatusnogradeyet'] = 'Todavía no hay calificación registrada';
$string['gradestatusnothreshold'] = 'Este curso no tiene establecida una calificación de aprobación';
$string['band_good'] = 'Bueno';
$string['band_neutral'] = 'Típico';
$string['band_watch'] = 'Vale la pena revisar';
$string['truncatednotice'] = 'Mostrando los primeros {$a->shown} de {$a->total}. Use los selectores de arriba para acotar esto.';
$string['model1desc_gradetrajectory'] = 'Cómo se comparan las puntuaciones STACK de este estudiante con la puntuación máxima.';
$string['model1sentence_gradetrajectory'] = 'Promedio de {$a->meanpercent}% en {$a->attempts} intento(s) finalizado(s).';
$string['model1desc_responselatencyanomaly'] = 'Si este estudiante responde de forma inverosímilmente rápida en comparación con la clase. Esto es solo una señal correlacional, nunca evidencia de mala conducta por sí sola.';
$string['model1sentence_responselatencyanomaly'] = 'Promedia {$a->userseconds}s entre intentos, frente a un promedio de clase de {$a->cohortseconds}s.';
$string['model1desc_disengagemententropy'] = 'Si los intentos de este estudiante parecen mecánicos (tiempos muy regulares, preguntas abandonadas) en lugar de una resolución de problemas genuina.';
$string['model1sentence_disengagemententropy'] = '{$a->abandonedcount} de {$a->attempts} intento(s) abandonado(s) antes de completarse.';
$string['model1desc_helpseekinggap'] = 'Si este estudiante busca ayuda (foros, glosario, otros recursos) después de una respuesta incorrecta con la misma frecuencia que sus compañeros.';
$string['model1sentence_helpseekinggap'] = 'Busca ayuda después del {$a->studentpercent}% de los errores, frente a un promedio de clase del {$a->baselinepercent}%.';
$string['model1desc_feedbackrevisiondistance'] = 'Si este estudiante cambia de forma significativa su respuesta después de ver la retroalimentación, o vuelve a enviar algo prácticamente igual.';
$string['model1sentence_feedbackrevisiondistance'] = 'Cambia su respuesta en un {$a->changepercent}% en promedio, a lo largo de {$a->revisions} revisión(es).';
$string['model2heading'] = 'Modelo 2: Calidad de la pregunta y del PRT';
$string['model2intro'] = 'Una fila por cada pregunta STACK (con el cuestionario al que pertenece indicado debajo), marcando aquellas que podrían valer la pena revisar por parte de un instructor a partir de cuatro señales sobre cómo responden realmente los estudiantes, incluido su PRT, la lógica de calificación paso a paso que comprueba la respuesta y da retroalimentación.';
$string['model2aboutbody'] = 'Lo que realmente se predice (el "objetivo") es: ¿la tasa de aprobación de esta pregunta cae por debajo de un umbral (50% por defecto, una configuración de administrador)? Los cuatro indicadores siguientes son la evidencia que un modelo entrenado usaría para eso. Hoy, antes de que se entrene ningún modelo, esta página simplemente muestra la lectura actual de cada indicador de forma directa. Nota: esta lectura de la tasa de aprobación y el indicador de dificultad provienen en última instancia de la misma tasa de aprobación, así que trate "necesita revisión" y "difícil" como señales relacionadas, no independientes.';
$string['model2noquestions'] = 'No hay preguntas STACK para mostrar con esta selección.';
$string['columnquestion'] = 'Pregunta';
$string['quizlabel'] = 'Cuestionario: {$a}';
$string['quizoptionlabel'] = '{$a->name} ({$a->count} pregunta(s) STACK)';
$string['needsreviewyes'] = 'Necesita revisión: {$a->passpercent}% de tasa de aprobación, por debajo del umbral del {$a->thresholdpercent}%';
$string['needsreviewno'] = 'Sin marcar: {$a->passpercent}% de tasa de aprobación, igual o por encima del umbral del {$a->thresholdpercent}%';
$string['model2desc_questiondifficultyirt'] = 'Qué tan difícil es esta pregunta en la práctica, según su tasa de aprobación empírica.';
$string['model2sentence_questiondifficultyirt'] = '{$a->passpercent}% de tasa de aprobación en {$a->attempts} intento(s) finalizado(s).';
$string['model2desc_syntaxerrorrate'] = 'Si la mayoría de las respuestas incorrectas de esta pregunta son errores de entrada/sintaxis (un problema de formato de entrada) en lugar de errores matemáticos genuinos.';
$string['model2sentence_syntaxerrorrate'] = '{$a->syntaxerrorcount} de {$a->totalfailed} intento(s) fallido(s) fueron errores de sintaxis/entrada.';
$string['model2desc_unreachednoderatio'] = 'Qué parte de la lógica de ramificación del PRT de esta pregunta nunca ha sido realmente ejercitada por un intento real, una candidata a poda si esto continúa así.';
$string['model2sentence_unreachednoderatio'] = '{$a->unreachedcount} de {$a->totalbranches} rama(s) del PRT nunca alcanzada(s).';
$string['model2desc_feedbackineffectiveness'] = 'Si los estudiantes que responden mal esto tienden a mejorar en su próximo intento más de lo que lo harían en una pregunta nueva, una estimación aproximada de si la retroalimentación realmente está ayudando.';
$string['model2sentence_feedbackineffectiveness'] = '{$a->improvepercent}% mejora después de un intento incorrecto, frente a una base del {$a->baselinepercent}% en el primer intento.';
$string['diagnosticsheading'] = 'Dashboard de diagnósticos';
$string['diagnosticsintrosummary'] = 'Qué significan el Sesgo de seed y la Cobertura de ramas PRT';
$string['diagnosticsintro'] = 'Dos comprobaciones por cada pregunta STACK, indicadas a continuación junto con el cuestionario al que pertenece. Cada vez que un estudiante intenta una pregunta STACK, Moodle elige un "seed" aleatorio que cambia sus números (por ejemplo, distintos coeficientes) manteniendo la misma estructura. El <strong>sesgo de seed</strong> comprueba si algunas de esas variantes de seed son injustamente más difíciles o más fáciles que otras, de modo que una calificación baja no sea simplemente "te tocó la versión más difícil". Cada pregunta STACK también califica las respuestas mediante un PRT (su lógica de calificación/retroalimentación paso a paso, formada por "ramas" para los distintos caminos de acierto/error). La <strong>cobertura de ramas PRT</strong> comprueba si alguna de esas ramas ha sido realmente activada alguna vez por la respuesta real de un estudiante. Una rama que nunca se alcanza es, o bien retroalimentación que funciona pero que aún nadie ha necesitado, o bien lógica muerta que vale la pena simplificar. Una insignia de "Vale la pena revisar" es una invitación a abrir esa pregunta y comprobar que tiene sentido según cómo la diseñó, no una prueba de que algo esté roto. Haga clic en una pregunta a continuación para ver las cifras completas detrás de sus insignias.';
$string['conceptdependencynote'] = 'El mapeo de dependencias entre conceptos (identificar qué fallos en unas preguntas tienden a predecir fallos en otras) todavía no está implementado en este plugin. El documento de arquitectura lo plantea como un trabajo de minería de secuencias fuera de línea, ajeno a una página de dashboard en vivo, y no algo para construir a medias aquí. Se menciona para que no simplemente desaparezca en silencio.';
$string['diagnosticsnoquestions'] = 'No hay preguntas STACK para mostrar con esta selección.';
$string['diagnosticsseedbiassentence'] = 'η²={$a->etasquared} ({$a->magnitude})';
$string['diagnosticsbloatedtreesentence'] = '{$a->unreached} de {$a->total} rama(s) nunca alcanzada(s)';
$string['modelpageintro'] = '<strong>El Modelo 1</strong> observa el comportamiento de cada estudiante y marca a quienes podrían estar en riesgo de no aprobar. <strong>El Modelo 2</strong> observa la lógica de calificación de cada pregunta y marca aquellas que podrían valer la pena que un profesor revise. Use el selector "Vista:" de abajo para alternar entre ellos. Ambos modelos están desactivados por defecto, por lo que todo lo que aparece a continuación es una lectura en vivo de cada señal hoy, no una predicción de IA entrenada. Un administrador puede activar y entrenar un modelo en Administración del sitio, Analíticas, Modelos. Una vez entrenado, las predicciones reales aparecerán junto a esta página en el propio informe de Perspectivas (Insights) de Moodle.';
$string['modelpageintrosummary'] = 'Acerca del Modelo 1 y el Modelo 2';
$string['diagnosticspageintro'] = 'El Dashboard de diagnósticos es un conjunto de informes estadísticos que no encajan en ninguno de los dos modelos: el Sesgo de seed y la Cobertura de ramas PRT, descritos a continuación. No son predicciones, solo cálculos directos a partir de los mismos datos de intentos.';
$string['diagnosticspageintrosummary'] = 'Acerca del Dashboard de diagnósticos';
$string['responsibleusecallout'] = 'Algunas cosas que conviene tener presentes al leer las marcas siguientes. Se trata de patrones estadísticos, no de una prueba de nada. Un tiempo de respuesta anómalo es una invitación a conversar con el estudiante, no una evidencia de mala conducta por sí sola. Los cursos pequeños mostrarán lecturas más ruidosas y menos fiables simplemente porque disponen de menos puntos de datos con los que trabajar. Cada número aquí describe lo que un estudiante hizo en este curso, no quién es.';
$string['responsibleusesummary'] = 'Uso responsable: algunas cosas que tener en cuenta';
$string['pdfsectionslabel'] = 'Incluir en el PDF:';
$string['pdfnorows'] = 'No hay nada que mostrar en esta sección: todavía no hay datos, o nada coincide con los filtros actuales.';
$string['pdffooternote'] = 'Dashboard de STACK q-type Analytics: lecturas de indicadores en vivo, no una predicción de IA entrenada';
$string['questionneedsreviewthreshold'] = 'Umbral de tasa de aprobación para "pregunta necesita revisión"';
$string['questionneedsreviewthreshold_desc'] = 'Una pregunta se etiqueta como "necesita revisión" (la etiqueta indirecta del Modelo 2) cuando su tasa de aprobación empírica cae por debajo de este valor (0.0-1.0). Consulte la advertencia sobre circularidad del §3.3 del documento de arquitectura antes de reducir este valor para perseguir un resultado en particular.';
$string['task:warmanalyticscache'] = 'Precalentar las cachés de resultados de STACK q-type Analytics';
$string['task:warmsingleview'] = 'Precalentar una vista de Análisis de cuestionarios/preguntas (cálculo en segundo plano bajo demanda)';
$string['generatinginbackground'] = 'Esto puede tardar un poco en cargar en un curso grande. Se está calculando en segundo plano. Esta página vuelve a comprobar automáticamente cada 20 segundos. No es necesario que la recargue usted mismo.';
$string['generatingstale'] = 'Esto ha estado en cola durante {$a} sin terminar. Eso es más tiempo del que ha tardado cualquier cálculo en segundo plano real en este sitio, por lo que lo más probable es que el cron de Moodle no se esté ejecutando, o que la tarea en segundo plano haya fallado o se haya quedado sin memoria. Pida a su administrador de Moodle que revise Administración del sitio → Servidor → Tareas programadas (¿se está ejecutando el cron?) y Administración del sitio → Servidor → Tareas → Registros de tareas (¿falló esta tarea?) en lugar de seguir esperando.';
$string['cronstatusheading'] = 'Estado del cron';
$string['cronstatuswarning'] = 'Este plugin depende de que el cron de Moodle se ejecute con regularidad.';
$string['detectedresourcesheading'] = 'Recursos del servidor detectados';
$string['detectedresources'] = 'Se detectaron {$a->cores} núcleos de CPU y {$a->memorygb} GB de RAM en este servidor. Workers paralelos de precalentamiento de caché recomendados: {$a->workers}. Se aplica automáticamente durante la instalación/actualización. Pulse "Volver a detectar" abajo si el hardware de este servidor ha cambiado desde entonces (por ejemplo, se pasó de un portátil a un servidor dedicado, o al revés).';
$string['detectedresourcesfailed'] = 'No se pudo detectar la CPU/RAM de este servidor (host inusual o contenedor restringido). En su lugar, se usan los valores predeterminados estáticos y conservadores originales de este plugin. La configuración "Workers paralelos de precalentamiento de caché" de abajo todavía se puede establecer manualmente.';
$string['redetectbutton'] = 'Volver a detectar y aplicar ahora';
$string['backgroundtimebudget'] = 'Presupuesto de tiempo para el cálculo en segundo plano bajo demanda (segundos)';
$string['backgroundtimebudget_desc'] = 'Cuando un visitante encuentra una caché fría (Análisis de cuestionarios, Análisis de preguntas o Visualización del proceso de solución), este plugin primero cronometra una obtención real de una muestra pequeña (~100 intentos) para el cuestionario real en este host real, y luego extrapola el costo total a partir de eso. Esto no es una estimación fija basada en el número de intentos, ya que el costo por intento realmente depende de la complejidad de la pregunta y de la velocidad del host (medido directamente: una pregunta aleatorizada simple se calculó aproximadamente 10 veces más barata por intento que una real y compleja). Si la estimación supera esta cantidad de segundos, el cálculo se entrega a una tarea en segundo plano en lugar de ejecutarse en esa misma solicitud. La ruta síncrona bajo demanda no realiza forking y no tiene un límite superior para el tamaño del cuestionario/curso, por lo que uno suficientemente grande puede superar el propio tiempo de espera de un proxy inverso (el valor predeterminado del plan gratuito/Pro de Cloudflare es de ~100s) antes de que la propia protección ignore_user_abort(true) de este plugin llegue siquiera a tener la oportunidad de ayudar. El visitante ve un aviso de "generándose en segundo plano" y necesita volver a visitar la página una vez que termine, en lugar de esperar en la misma solicitud. Establézcalo en 0 para desactivarlo y calcular siempre en línea (el comportamiento antiguo). El valor predeterminado deja un margen real por debajo de un tiempo de espera de proxy de 100s incluso después de añadir el paso de análisis y el propio sesgo conservador de la estimación por muestreo.';
$string['parallelworkers'] = 'Workers paralelos de precalentamiento de caché';
$string['parallelworkers_desc'] = 'La tarea programada "Precalentar las cachés de resultados de STACK q-type Analytics" crea (fork) hasta esta cantidad de procesos worker para obtener los datos de intentos de varios cuestionarios de forma concurrente (solo CLI/cron; esto no afecta a las páginas web bajo demanda, que siempre obtienen los datos de forma secuencial). El resumen de respuestas de cada pregunta STACK se califica en vivo mediante CAS cada vez que se obtiene, lo cual está limitado por E/S al esperar al backend de Maxima, por lo que ejecutar las obtenciones de varios cuestionarios a la vez puede reducir de forma significativa el tiempo de precalentamiento de un curso grande. El valor 1 desactiva el forking (vuelve a la obtención secuencial original). Mantenga este valor igual o por debajo de la propia capacidad de workers/cola de su backend de Maxima y de las conexiones disponibles de su base de datos. Cada worker abre su propia conexión a la base de datos y realiza llamadas a CAS de forma concurrente con las demás. Vea también "Límite de memoria de los workers de precalentamiento de caché" a continuación. Ambas configuraciones deben dimensionarse conjuntamente en función de la RAM real disponible en su servidor.';
$string['parallelworkermemory'] = 'Límite de memoria de los workers de precalentamiento de caché (MB)';
$string['parallelworkermemory_desc'] = 'El límite de memoria de PHP (en MB) permitido para cada proceso worker de precalentamiento de caché, incluido el caso sin forking (una única obtención también usa este límite). La propia obtención de cada worker transmite a disco los registros de un cuestionario de uno en uno, en lugar de acumular toda su parte en memoria, pero los datos de intentos de un único cuestionario muy grande todavía tienen que caber en memoria por sí solos. Medido directamente, un solo cuestionario con 2136 intentos alcanzó un pico de justo por debajo de 1 GB, razón por la cual el valor predeterminado aquí es superior a eso. Dimensione esto junto con "Workers paralelos de precalentamiento de caché": si esa cantidad de workers puede ejecutarse al mismo tiempo, asegúrese de que workers × este valor quepa cómodamente dentro de la RAM real disponible de su servidor, dejando margen para MariaDB/Postgres, el backend de Maxima y todo lo demás que ya se esté ejecutando. Excederse no solo hace que falle limpiamente este curso en particular, sino que arriesga a que el propio mecanismo del kernel para matar procesos por falta de memoria (OOM killer) elija en su lugar un proceso no relacionado (la base de datos, Maxima), lo cual es un problema mucho mayor. En caso de duda, reduzca parallelworkers en lugar de aumentar este valor por encima de lo que sabe que realmente está libre.';
$string['lowtrafficfloor'] = 'Umbral mínimo de "tráfico bajo" del árbol saturado';
$string['lowtrafficfloor_desc'] = 'En el Dashboard de diagnósticos, una rama del PRT con al menos un recorrido observado, pero menos que esta cantidad, se reporta como "tráfico bajo" (necesita una revisión humana) en lugar de "nunca alcanzada" (candidata a poda).';
$string['helpseekinglookback'] = 'Ventana retrospectiva de búsqueda de ayuda (segundos)';
$string['helpseekinglookback_desc'] = 'Cuánto tiempo después de un fallo en una pregunta STACK todavía cuenta un acceso a un foro/glosario/recurso como "buscar ayuda para ello", para el indicador de brecha de búsqueda de ayuda. El valor predeterminado es una hora.';
