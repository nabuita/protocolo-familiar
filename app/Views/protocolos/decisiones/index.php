<?php
declare(strict_types=1);
$e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$option = static function (string $value, ?string $selected) use ($e): void {
    ?><option value="<?= $e($value) ?>" <?= $selected === $value ? 'selected' : '' ?>><?= $e($value) ?></option><?php
};
$date = static fn(mixed $value): string => is_string($value) ? substr($value, 0, 10) : '';
$stateClass = static fn(mixed $value): string => strtolower(str_replace([' ', '/', 'á', 'é', 'í', 'ó', 'ú', 'ñ'], ['-', '-', 'a', 'e', 'i', 'o', 'u', 'n'], (string) $value));
$decisionGroups = [];
foreach ($decisionRows as $row) {
    $key = (string) $row['categoria_codigo'];
    if (!isset($decisionGroups[$key])) {
        $decisionGroups[$key] = [
            'codigo' => $row['categoria_codigo'],
            'nombre' => $row['categoria_nombre'],
            'rows' => [],
            'total' => 0,
            'respondidas' => 0,
            'pendientes' => 0,
            'aprobadas' => 0,
            'implementadas' => 0,
            'verificadas' => 0,
            'vencidas' => 0,
            'documentos_pendientes' => 0,
            'provisionales' => 0,
            'alertas_riesgo' => 0,
        ];
    }
    $decisionGroups[$key]['rows'][] = $row;
    $decisionGroups[$key]['total']++;
    $decisionGroups[$key]['respondidas'] += trim((string) ($row['respuesta'] ?? '')) !== '' || trim((string) ($row['responsable'] ?? '')) !== '' || $row['estado_decision'] !== 'Pendiente de analizar' ? 1 : 0;
    $decisionGroups[$key]['pendientes'] += $row['estado_decision'] === 'Pendiente de analizar' ? 1 : 0;
    $decisionGroups[$key]['aprobadas'] += $row['estado_decision'] === 'Aprobada' ? 1 : 0;
    $decisionGroups[$key]['implementadas'] += $row['estado_implementacion'] === 'Implementada' ? 1 : 0;
    $decisionGroups[$key]['verificadas'] += $row['estado_implementacion'] === 'Verificada' ? 1 : 0;
    $alerts = is_array($row['alertas_calculadas'] ?? null) ? $row['alertas_calculadas'] : [];
    $decisionGroups[$key]['vencidas'] += in_array('Vencida', $alerts, true) ? 1 : 0;
    $decisionGroups[$key]['alertas_riesgo'] += in_array('Posible riesgo pendiente de revisión humana', $alerts, true) ? 1 : 0;
    $decisionGroups[$key]['documentos_pendientes'] += (int) (($row['documentos']['resumen']['pendientes'] ?? 0) > 0);
    $decisionGroups[$key]['provisionales'] += (int) (($row['documentos']['resumen']['provisionales'] ?? 0) > 0);
}
$activeDecisionGroup = $decisionGroups['CAT-01'] ?? reset($decisionGroups);
$activeDecisionProgress = $activeDecisionGroup && (int) $activeDecisionGroup['total'] > 0
    ? (int) round(((int) $activeDecisionGroup['verificadas'] / (int) $activeDecisionGroup['total']) * 100)
    : 0;
$legalReferenceBaseUrls = [
    'constitucion' => 'https://www.secretariasenado.gov.co/senado/basedoc/constitucion_politica_1991.html',
    'codigo de comercio' => 'https://www.secretariasenado.gov.co/senado/basedoc/codigo_comercio.html',
    'codigo civil' => 'https://www.secretariasenado.gov.co/senado/basedoc/codigo_civil.html',
    'codigo sustantivo del trabajo' => 'https://www.secretariasenado.gov.co/senado/basedoc/codigo_sustantivo_trabajo.html',
    'codigo general del proceso' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_1564_2012.html',
    'codigo penal' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_0599_2000.html',
    'estatuto tributario' => 'https://www.secretariasenado.gov.co/senado/basedoc/estatuto_tributario.html',
    'ley 222' => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=6739',
    'ley 1258' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_1258_2008.html',
    'ley 1581' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_1581_2012.html',
    'ley 1098' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_1098_2006.html',
    'ley 54' => 'https://www.suin-juriscol.gov.co/viewDocument.asp?id=1607782',
    'ley 979' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_0979_2005.html',
    'ley 1563' => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=48366',
    'ley 256' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_0256_1996.html',
    'ley 23' => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=3431',
    'ley 1273' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_1273_2009.html',
    'ley 1996' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_1996_2019.html',
    'ley 1010' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_1010_2006.html',
    'ley 789' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_0789_2002.html',
    'ley 820' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_0820_2003.html',
    'ley 99' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_0099_1993.html',
    'ley 1676' => 'https://www.secretariasenado.gov.co/senado/basedoc/ley_1676_2013.html',
    'decreto 46' => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=228530',
    'decreto 2150' => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=85041',
    'decreto 2420' => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=76745',
    'decision andina 486' => 'https://www.comunidadandina.org/StaticFiles/DocOf/DEC486.pdf',
    'decision andina 351' => 'https://www.comunidadandina.org/StaticFiles/DocOf/DEC351.pdf',
    'guia colombiana' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    'guia supersociedades' => 'https://www.supersociedades.gov.co/documents/20122/1229078/GUIA-GESTION-CONFLICTO-INTERESES.pdf',
    'supersociedades' => 'https://www.supersociedades.gov.co/',
    'sic' => 'https://www.sic.gov.co/',
    'dnda' => 'https://www.derechodeautor.gov.co/',
    'dnp guia valoracion pi' => 'https://www.ige.ch/fileadmin/user_upload/recht/entwicklungszusammenarbeit/2025_DNP_GuIa_valoracion_propiedad_intelectual_para_emprendedores_y_mipymes.pdf',
    'c-014 de 2010' => 'https://www.corteconstitucional.gov.co/relatoria/2010/c-014-10.htm',
    'c-305 de 2013' => 'https://www.corteconstitucional.gov.co/relatoria/2013/c-305-13.htm',
    'c-278 de 2014' => 'https://www.corteconstitucional.gov.co/relatoria/2014/c-278-14.htm',
    'c-058 de 2018' => 'https://www.corteconstitucional.gov.co/relatoria/2018/c-058-18.htm',
    'c-192 de 2023' => 'https://www.corteconstitucional.gov.co/relatoria/2023/c-192-23.htm',
];
$legalReferenceUrl = static function (array $reference) use ($legalReferenceBaseUrls): string {
    $explicitUrl = trim((string) ($reference['url'] ?? ''));
    if ($explicitUrl !== '') {
        return $explicitUrl;
    }
    $haystack = strtolower(str_replace(['Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±'], ['a', 'e', 'i', 'o', 'u', 'n'], trim(implode(' ', [
        (string) ($reference['norma'] ?? ''),
        (string) ($reference['articulo'] ?? ''),
        (string) ($reference['texto'] ?? ''),
    ]))));
    foreach ($legalReferenceBaseUrls as $needle => $url) {
        if (str_contains($haystack, $needle)) {
            return $url;
        }
    }
    return '';
};
$cat01Academy = [
    'DEC-001' => [
        'decision' => 'Definir para que existe la familia empresaria y que quiere preservar al tomar decisiones sobre empresa, patrimonio y relaciones familiares.',
        'fundamento' => 'Ley 1258 de 2008, arts. 17 y 24: libertad de organizacion de la S.A.S. y acuerdos de accionistas sobre asuntos licitos. Ley 222 de 1995, arts. 19, 20 y 21: decisiones, reuniones y actas cuando el acuerdo de familia deba formalizarse en organos societarios. Guia Colombiana de Gobierno Corporativo, Medida 31: protocolo para regular familia, negocio y propiedad.',
        'ejemplo' => 'La familia declara que su proposito es preservar y hacer crecer el patrimonio empresarial con unidad familiar, transparencia y continuidad generacional, sin sacrificar la sostenibilidad de las empresas.',
        'claro' => 'Debe quedar claro: alcance del proposito, limites, responsables de custodiarlo y forma de revisarlo.',
        'precedente' => 'Corte Constitucional C-014 de 2010: reconoce la flexibilidad de la S.A.S. y la intervencion minima del legislador; doctrina Supersociedades: el protocolo de familia es instrumento preventivo y paraestatutario.',
    ],
    'DEC-002' => [
        'decision' => 'Definir que legado desean transmitir los fundadores: valores, patrimonio, reputacion, cultura empresarial, oportunidades o continuidad.',
        'fundamento' => 'Ley 1258 de 2008, arts. 5, 17 y 24: estatutos, estructura organica y acuerdos de accionistas pueden ordenar reglas patrimoniales y de voto. Ley 222 de 1995, arts. 19 a 21: el legado con efectos societarios debe documentarse en decisiones y actas cuando corresponda.',
        'ejemplo' => 'El legado familiar sera conservar empresas sostenibles, buen nombre, educacion de las siguientes generaciones y respeto por el origen empresarial.',
        'claro' => 'Debe quedar claro: que se transmite, a quienes, por que medios y que no puede comprometerse.',
        'precedente' => 'Doctrina Supersociedades sobre protocolos: el protocolo no reemplaza estatutos ni sucesion, pero puede servir como acuerdo preventivo que luego se desarrolla en documentos societarios o patrimoniales.',
    ],
    'DEC-003' => [
        'decision' => 'Escoger valores obligatorios que orienten conductas familiares, societarias y empresariales.',
        'fundamento' => 'Ley 222 de 1995, art. 23: deberes de buena fe, lealtad y diligencia de los administradores; art. 24: responsabilidad de administradores. Ley 1258 de 2008, art. 27: aplica el regimen de responsabilidad de administradores a la S.A.S.',
        'ejemplo' => 'Seran valores obligatorios: honestidad, respeto, austeridad, responsabilidad, transparencia, cumplimiento de la palabra y proteccion del buen nombre.',
        'claro' => 'Debe quedar claro: valores, conductas esperadas, conductas prohibidas y consecuencia de incumplimiento.',
        'precedente' => 'Corte Constitucional C-276 de 2025 resalta la relevancia de los administradores y sus deberes dentro del gobierno societario; Guia de conflictos de intereses de Supersociedades desarrolla el art. 23 de la Ley 222.',
    ],
    'DEC-004' => [
        'decision' => 'Definir la vision de largo plazo sobre empresas, patrimonio, continuidad, crecimiento, venta o diversificacion.',
        'fundamento' => 'Ley 1258 de 2008, arts. 17, 24 y 45: organizacion societaria, acuerdos de accionistas y remision a normas societarias generales. Ley 222 de 1995, arts. 19 a 21: formalizacion de decisiones. Guia Colombiana, Medidas 28 a 31: consejo de familia y protocolo.',
        'ejemplo' => 'La familia proyecta conservar el control patrimonial por al menos dos generaciones, profesionalizar la administracion y diversificar inversiones con criterios de riesgo definidos.',
        'claro' => 'Debe quedar claro: horizonte, criterio de crecimiento, posibilidad de venta, reinversion y responsables.',
        'precedente' => 'Corte Constitucional C-014 de 2010 sirve como criterio sobre flexibilidad de la S.A.S.; las reglas familiares deben aterrizarse en organos, actas o acuerdos cuando tengan efectos societarios.',
    ],
    'DEC-005' => [
        'decision' => 'Decidir si la voluntad es conservar las empresas entre generaciones o permitir venta bajo condiciones.',
        'fundamento' => 'Ley 1258 de 2008, art. 24: acuerdos de accionistas sobre compra, venta, preferencia, restricciones de transferencia, voto y otros asuntos licitos. Ley 222 de 1995, arts. 19 a 21: decisiones y actas. Codigo de Comercio y estatutos: mayorias y organos competentes.',
        'ejemplo' => 'La regla sera conservar las empresas familiares, salvo oferta extraordinaria aprobada por el organo societario competente y concepto previo del consejo de familia.',
        'claro' => 'Debe quedar claro: regla de conservacion/venta, condiciones, mayorias, organo competente y excepciones.',
        'precedente' => 'Doctrina Supersociedades sobre acuerdos y protocolos: las reglas familiares pueden orientar, pero no deben contrariar estatutos ni competencias legales de los organos sociales.',
    ],
    'DEC-006' => [
        'decision' => 'Definir compromisos minimos para preservar la unidad familiar y prevenir conflictos.',
        'fundamento' => 'Ley 1258 de 2008, art. 40: resolucion de conflictos societarios cuando aplique. Ley 222 de 1995, arts. 19 a 21: acuerdos y actas. Guia Colombiana, Medidas 28 a 31: organos de familia, consejo y protocolo como instrumentos preventivos.',
        'ejemplo' => 'Los familiares se obligan moral y documentalmente a dialogar primero en consejo de familia, evitar ataques publicos y acudir a mediacion antes de acciones judiciales entre miembros.',
        'claro' => 'Debe quedar claro: conductas esperadas, canal de dialogo, instancia de mediacion y responsables.',
        'precedente' => 'Corte Constitucional C-014 de 2010 analizo la competencia jurisdiccional para conflictos societarios en S.A.S.; doctrina Supersociedades califica el protocolo como mecanismo preventivo de conflictos.',
    ],
    'DEC-007' => [
        'decision' => 'Separar temas familiares de decisiones empresariales y societarias.',
        'fundamento' => 'Ley 222 de 1995, arts. 22, 23 y 24: administradores, deberes y responsabilidad. Ley 1258 de 2008, arts. 17 y 27: estructura organica libre y responsabilidad de administradores. Guia Colombiana, Medida 32: roles, deberes y limites de familiares frente a la sociedad.',
        'ejemplo' => 'Los asuntos afectivos y familiares se tratan en consejo de familia; las decisiones societarias se toman en los organos previstos por la ley y los estatutos.',
        'claro' => 'Debe quedar claro: que decide la familia, que decide la empresa y que decide cada organo formal.',
        'precedente' => 'Corte Constitucional C-276 de 2025 recuerda la importancia de identificar administradores; el protocolo debe evitar que el rol familiar invada competencias legales societarias.',
    ],
    'DEC-008' => [
        'decision' => 'Diferenciar roles: familiar, propietario, empleado, administrador, beneficiario o tercero relacionado.',
        'fundamento' => 'Guia Colombiana, Medida 32: el protocolo debe establecer roles, funciones, deberes, responsabilidades y limites de familiares como socios, accionistas, empleados o administradores. Ley 222 de 1995, arts. 22 a 24; Ley 1258 de 2008, art. 27.',
        'ejemplo' => 'Ser familiar no da derecho automatico a empleo, cargo directivo, remuneracion o uso de activos; cada rol exige requisitos y aprobacion del organo competente.',
        'claro' => 'Debe quedar claro: roles, derechos, deberes, limites, acceso a cargos y conflictos de interes.',
        'precedente' => 'Guia de conflictos de intereses de Supersociedades desarrolla el deber del art. 23 de la Ley 222; es util para separar familia, propiedad, empleo y administracion.',
    ],
    'DEC-009' => [
        'decision' => 'Definir reglas para proteger reputacion, nombre familiar y relacion con empresas.',
        'fundamento' => 'Ley 222 de 1995, art. 23: deber de lealtad, reserva y conducta en interes de la sociedad para administradores. Ley 1258 de 2008, art. 24: acuerdos de accionistas sobre asuntos licitos. Guia Colombiana, Medidas 31 y 32: protocolo y limites de actuacion familiar.',
        'ejemplo' => 'Ningun familiar podra usar el nombre de la familia o de las empresas para negocios personales sin autorizacion; los conflictos se manejaran por canales internos.',
        'claro' => 'Debe quedar claro: usos permitidos, voceros, confidencialidad, redes sociales y consecuencias.',
        'precedente' => 'Doctrina Supersociedades sobre deberes de administradores y conflictos de interes: las reglas reputacionales deben conectarse con deber de lealtad, reserva y aprobaciones cuando aplique.',
    ],
    'DEC-010' => [
        'decision' => 'Definir que memoria, historia, tradiciones y cultura empresarial deben conservarse.',
        'fundamento' => 'Ley 222 de 1995, art. 21: actas como soporte de decisiones; arts. 19 y 20: reuniones y decisiones documentadas. Ley 1258 de 2008, arts. 5 y 17: estatutos y organizacion interna. Guia Colombiana, Medida 31: protocolo como acuerdo que regula familia, negocio y propiedad.',
        'ejemplo' => 'La familia conservara una resena historica, archivo documental, valores fundacionales y encuentros anuales para transmitir cultura empresarial.',
        'claro' => 'Debe quedar claro: que se conserva, responsable, soporte documental, periodicidad y acceso.',
        'precedente' => 'Doctrina Supersociedades sobre protocolo de familia: al ser un instrumento preventivo, conviene conservar actas, versiones y evidencias de aprobacion para trazabilidad.',
    ],
];
$cat01LegalReferences = [
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Art. 19 - Reuniones no presenciales',
        'texto' => 'Permite deliberar y decidir por medios de comunicacion simultanea o sucesiva, siempre que pueda probarse la participacion. Sirve para dejar actas de decisiones familiares con efectos societarios cuando no todos estan presentes.',
        'uso' => 'Util para aprobar o revisar el acta unica CAT-01 por reunion virtual, llamada o comunicacion equivalente.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Art. 20 - Decisiones por comunicacion escrita',
        'texto' => 'Permite adoptar decisiones por escrito cuando todos los socios o miembros expresan el sentido de su voto. Exige soporte documental y trazabilidad.',
        'uso' => 'Util si la familia aprueba CAT-01 mediante circulacion del documento y confirmacion escrita.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Art. 21 - Actas',
        'texto' => 'Las decisiones deben constar en actas con los elementos que permitan probar lo decidido, participantes, fecha y sentido de la decision.',
        'uso' => 'Base para que CAT-01 quede en un solo documento soporte con las 10 decisiones individualizadas.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Art. 22 - Administradores',
        'texto' => 'Identifica como administradores al representante legal, liquidador, factor, miembros de juntas o consejos directivos y quienes ejerzan esas funciones segun estatutos.',
        'uso' => 'Sirve para separar el rol familiar del rol de administrador en DEC-007 y DEC-008.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Art. 23 - Deberes de administradores',
        'texto' => 'Ordena actuar con buena fe, lealtad y diligencia, en interes de la sociedad y teniendo en cuenta los intereses de los asociados; incluye deberes sobre cumplimiento, reserva, trato equitativo y conflictos de interes.',
        'uso' => 'Soporta valores, buen nombre, conflictos de interes, separacion familia-empresa y reglas de conducta.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Art. 24 - Responsabilidad de administradores',
        'texto' => 'Establece responsabilidad de los administradores por perjuicios derivados de dolo o culpa y refuerza la importancia de reglas claras y verificables.',
        'uso' => 'Sirve para que las reglas CAT-01 no sean solo aspiracionales cuando involucren administradores.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Art. 5 - Documento de constitucion S.A.S.',
        'texto' => 'Define contenidos minimos del documento de constitucion y estatutos de la S.A.S., incluyendo identificacion, sociedad, domicilio, duracion, objeto, capital y administracion.',
        'uso' => 'Sirve para armonizar el protocolo familiar con estatutos y estructura patrimonial.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Art. 17 - Organizacion de la sociedad',
        'texto' => 'Permite determinar libremente en estatutos la estructura organica y las normas de funcionamiento de la S.A.S., dentro del marco legal.',
        'uso' => 'Soporta reglas flexibles de gobierno familiar cuando deban traducirse a estatutos o politicas societarias.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Art. 24 - Acuerdos de accionistas',
        'texto' => 'Permite acuerdos sobre compra o venta de acciones, preferencia, restricciones de transferencia, voto, representacion en asamblea y otros asuntos licitos; para ser acatados por la compania deben depositarse en la administracion y respetar el termino legal.',
        'uso' => 'Base directa para continuidad, legado, venta, voto, restricciones y reglas entre accionistas familiares.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Art. 27 - Responsabilidad de administradores',
        'texto' => 'Remite a las reglas de responsabilidad de administradores de la Ley 222 de 1995 para la S.A.S.',
        'uso' => 'Conecta los deberes de administradores con familiares que tambien sean representantes, directores o miembros de junta.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Art. 40 - Resolucion de conflictos societarios',
        'texto' => 'Previo competencia jurisdiccional especializada para diferencias societarias de la S.A.S. relacionadas con asuntos previstos en la ley.',
        'uso' => 'Soporta que CAT-01 incluya mecanismos previos de dialogo, mediacion y escalamiento.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Art. 45 - Remision normativa',
        'texto' => 'Indica que en lo no previsto para la S.A.S. se acude a reglas aplicables segun la ley y al regimen societario correspondiente.',
        'uso' => 'Recuerda que el protocolo debe armonizarse con estatutos, Codigo de Comercio y normas societarias generales.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 28, 31, 32, 33 y 34',
        'texto' => 'Recomienda organos de familia, protocolo de familia, definicion de roles, deberes y limites, y reglas para operaciones entre familiares y sociedad.',
        'uso' => 'Marco consultivo principal para que CAT-01 regule familia, propiedad y empresa de forma ordenada.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Corte Constitucional / Supersociedades',
        'articulo' => 'C-014 de 2010, C-276 de 2025 y doctrina administrativa',
        'texto' => 'La jurisprudencia y doctrina consultada refuerzan la flexibilidad societaria, la relevancia de administradores y el valor preventivo del protocolo de familia.',
        'uso' => 'Sirve como soporte interpretativo, no como reemplazo de asesoria juridica para el caso concreto.',
    ],
];
$cat02Academy = [
    'DEC-011' => [
        'decision' => 'Definir que sociedades, establecimientos, negocios, patrimonios autonomos, vehiculos de inversion o emprendimientos quedan cubiertos por el protocolo.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 5 (estatutos e identificacion de la sociedad), art. 17 (estructura y reglas internas de la S.A.S.), art. 24 (acuerdos de accionistas sobre asuntos licitos) y art. 45 (remision normativa). Ley 222/1995 arts. 19, 20 y 21 para reuniones, decisiones escritas y actas.',
        'ejemplo' => 'El protocolo aplicara a las sociedades donde la familia tenga control directo o indirecto, a los negocios familiares operativos y a los vehiculos patrimoniales listados en un anexo actualizado anualmente.',
        'claro' => 'Debe quedar claro: entidades incluidas, entidades excluidas, criterio de inclusion futura, responsable de actualizar el listado y soporte documental.',
        'precedente' => 'Guia Colombiana de Gobierno Corporativo, Medida 31: el protocolo regula la relacion entre familia, negocio y propiedad; doctrina Supersociedades lo reconoce como instrumento preventivo.',
    ],
    'DEC-012' => [
        'decision' => 'Definir si el protocolo cubre solo empresas o tambien inmuebles, inversiones, vehiculos, marcas, cuentas, derechos economicos u otros activos familiares.',
        'fundamento' => 'Articulos guia: Constitucion art. 58 (propiedad privada y derechos adquiridos). Ley 1258/2008 art. 24 (acuerdos sobre acciones y asuntos licitos). Ley 222/1995 art. 21 (actas que documenten el alcance patrimonial aprobado).',
        'ejemplo' => 'El protocolo incluira participaciones societarias, inmuebles productivos, marcas familiares, inversiones y activos usados por la familia o las empresas, sin alterar la titularidad legal registrada.',
        'claro' => 'Debe quedar claro: activo incluido, titular, uso permitido, administracion, reglas de informacion y diferencia entre propiedad legal y reglas familiares.',
        'precedente' => 'La regla familiar no cambia por si sola la titularidad; debe armonizarse con escrituras, registros, estatutos, contratos y documentos de soporte.',
    ],
    'DEC-013' => [
        'decision' => 'Definir hasta que grado de consanguinidad, afinidad o parentesco civil se considera familia para efectos del protocolo.',
        'fundamento' => 'Articulos guia: Constitucion art. 42 (familia por vinculos naturales o juridicos e igualdad de hijos). Codigo Civil art. 35 (parentesco por consanguinidad), art. 37 (conteo de grados) y art. 47 (parentesco por afinidad).',
        'ejemplo' => 'Para participacion en asamblea de familia se incluiran descendientes directos, conyuges o companeros permanentes invitados bajo reglas especiales, y familiares hasta cuarto grado cuando el consejo de familia lo apruebe.',
        'claro' => 'Debe quedar claro: grados incluidos, derechos de cada grupo, diferencias entre informacion, voz, voto, beneficios y obligaciones.',
        'precedente' => 'Corte Constitucional C-075 de 2021 explica el parentesco de consanguinidad desde el art. 35 del Codigo Civil; el protocolo debe usar definiciones juridicas, no solo costumbre familiar.',
    ],
    'DEC-014' => [
        'decision' => 'Definir expresamente el tratamiento de hijos adoptivos y parentesco civil dentro del protocolo.',
        'fundamento' => 'Articulos guia: Constitucion art. 42 (hijos adoptados, biologicos o asistidos tienen iguales derechos y deberes). Ley 1098/2006 art. 61 (adopcion como medida de proteccion) y art. 64 (efectos juridicos de la adopcion y parentesco civil).',
        'ejemplo' => 'Los hijos adoptivos tendran el mismo tratamiento familiar, patrimonial, formativo y de adhesion al protocolo que los hijos biologicos, sin discriminacion por el origen de la filiacion.',
        'claro' => 'Debe quedar claro: igualdad de trato, documentos de soporte, proteccion de intimidad y limites para divulgar informacion sensible.',
        'precedente' => 'Corte Constitucional C-192 de 2023 y C-058 de 2018 reiteran efectos juridicos de la adopcion y creacion del parentesco civil.',
    ],
    'DEC-015' => [
        'decision' => 'Definir si los conyuges participan, en que espacios, con que derechos de informacion, voz, voto, confidencialidad y restricciones.',
        'fundamento' => 'Articulos guia: Constitucion art. 42 (familia, igualdad de derechos y deberes de la pareja, intimidad familiar). Codigo Civil art. 47 (afinidad con parientes del conyuge). Regimen patrimonial matrimonial aplicable segun capitulaciones, sociedad conyugal o separacion.',
        'ejemplo' => 'Los conyuges podran participar en actividades de integracion y formacion, pero no tendran voto en decisiones de propiedad salvo que sean accionistas o exista autorizacion expresa.',
        'claro' => 'Debe quedar claro: espacios permitidos, informacion accesible, confidencialidad, conflicto de interes y efectos de separacion o divorcio.',
        'precedente' => 'El protocolo debe distinguir vinculo familiar, derechos societarios y regimen patrimonial matrimonial; no todo conyuge adquiere derechos de accionista por participar en la familia.',
    ],
    'DEC-016' => [
        'decision' => 'Definir el tratamiento de companeros permanentes y uniones maritales de hecho en organos, informacion, beneficios y obligaciones.',
        'fundamento' => 'Articulos guia: Constitucion art. 42 (proteccion integral de la familia y voluntad responsable de conformarla). Ley 54/1990 art. 1 (definicion de union marital de hecho) y art. 2 (presuncion de sociedad patrimonial entre companeros permanentes).',
        'ejemplo' => 'Los companeros permanentes acreditados podran asistir a actividades familiares y, si manejan informacion, deberan firmar compromiso de confidencialidad y adhesion limitada.',
        'claro' => 'Debe quedar claro: forma de acreditar la union, tratamiento patrimonial, confidencialidad, participacion y efectos de terminacion de la relacion.',
        'precedente' => 'La Corte Constitucional ha protegido diversas formas de familia bajo el art. 42; la regla interna debe evitar discriminacion y a la vez proteger propiedad e informacion empresarial.',
    ],
    'DEC-017' => [
        'decision' => 'Definir derechos y limites de familiares que no son accionistas: informacion, formacion, participacion, beneficios y confidencialidad.',
        'fundamento' => 'Articulos guia: Constitucion art. 15 (intimidad, buen nombre y habeas data) y art. 42 (familia). Ley 1581/2012 art. 4 (principios de tratamiento de datos), art. 5 (datos sensibles), art. 9 (autorizacion del titular) y art. 17 (deberes del responsable). Ley 1258/2008 art. 24 si hay acuerdos de accionistas.',
        'ejemplo' => 'Los familiares no accionistas tendran acceso a informacion pedagogica y actividades de formacion, pero no a informacion reservada de la sociedad salvo autorizacion y acuerdo de confidencialidad.',
        'claro' => 'Debe quedar claro: que informacion reciben, si tienen voz, si tienen voto, deber de reserva y diferencia frente a los accionistas.',
        'precedente' => 'Guia Colombiana, Medida 32: el protocolo debe diferenciar roles de familiares como socios, accionistas, empleados o administradores.',
    ],
    'DEC-018' => [
        'decision' => 'Definir como se incorporan las nuevas generaciones al protocolo al llegar a cierta edad o adquirir capacidad para participar.',
        'fundamento' => 'Articulos guia: Constitucion art. 42 (igualdad de hijos y proteccion familiar). Ley 1581/2012 arts. 4, 9 y 17 (principios, autorizacion y deberes sobre datos). Reglas civiles de capacidad y representacion de menores para adhesion pedagogica o futura.',
        'ejemplo' => 'Los descendientes recibiran formacion familiar desde edad definida; al cumplir mayoria de edad se les presentara el protocolo y firmaran adhesion, confidencialidad y compromiso de actualizacion de datos.',
        'claro' => 'Debe quedar claro: edad de ingreso, proceso pedagogico, firma de adhesion, representantes de menores y datos que se trataran.',
        'precedente' => 'El protocolo como instrumento preventivo permite preparar sucesion y continuidad sin esperar a que exista conflicto entre generaciones.',
    ],
    'DEC-019' => [
        'decision' => 'Definir si quien adquiera acciones familiares debe adherirse al protocolo como condicion familiar o societaria permitida.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 (acuerdos de accionistas sobre compra/venta de acciones, preferencia, restricciones de transferencia, voto, representacion y otros asuntos licitos). Ley 1258/2008 arts. 5 y 17 para armonizar con estatutos y estructura organica.',
        'ejemplo' => 'Todo accionista familiar nuevo debera recibir copia del protocolo, firmar adhesion y respetar las reglas de informacion, voto coordinado, confidencialidad y resolucion de conflictos.',
        'claro' => 'Debe quedar claro: momento de adhesion, consecuencias de no adherir, deposito del acuerdo, compatibilidad con estatutos y plazo de vigencia.',
        'precedente' => 'Supersociedades ha reiterado que acuerdos de accionistas en S.A.S. deben respetar la ley, estatutos y deposito para ser exigibles frente a la compania.',
    ],
    'DEC-020' => [
        'decision' => 'Definir duracion, revision, actualizacion, terminacion y reglas de modificacion del protocolo.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 (vigencia del acuerdo de accionistas y prorroga segun requisitos legales). Ley 222/1995 arts. 19, 20 y 21 (reuniones, decisiones escritas y actas). Codigo Civil art. 1602 como regla general de fuerza obligatoria de contratos validos.',
        'ejemplo' => 'El protocolo tendra revision ordinaria cada dos anos y revision extraordinaria por cambio generacional, venta relevante, ingreso de nuevos accionistas, conflicto grave o reforma estatutaria.',
        'claro' => 'Debe quedar claro: vigencia, mayoria para modificar, periodicidad, causal de revision, responsable y documento soporte de cada version.',
        'precedente' => 'La trazabilidad de versiones evita ambiguedad sobre que regla estaba vigente y quien la acepto.',
    ],
];
$cat02LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Art. 15 - Intimidad, buen nombre y habeas data',
        'texto' => 'Permite explicar por que el censo familiar, genograma, datos de conyuges, companeros, adopcion o informacion patrimonial requieren autorizacion, finalidad y reserva.',
        'uso' => 'Aplica a DEC-017 y DEC-018; tambien a cualquier anexo con datos familiares.',
    ],
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Art. 42 - Familia e igualdad de hijos',
        'texto' => 'Reconoce la familia por vinculos naturales o juridicos, protege la intimidad familiar y establece igualdad de hijos biologicos, adoptivos o procreados con asistencia cientifica.',
        'uso' => 'Aplica a DEC-013, DEC-014, DEC-015, DEC-016 y DEC-018.',
    ],
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Art. 58 - Propiedad privada',
        'texto' => 'Protege propiedad privada y derechos adquiridos. Sirve para explicar que el protocolo regula conducta familiar, pero no cambia por si solo la titularidad legal de bienes.',
        'uso' => 'Aplica a DEC-011 y DEC-012.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Arts. 35, 37 y 47 - Parentesco',
        'texto' => 'Art. 35 define consanguinidad; art. 37 cuenta grados por generaciones; art. 47 regula afinidad con parientes del conyuge.',
        'uso' => 'Aplica a DEC-013 y DEC-015.',
    ],
    [
        'norma' => 'Ley 1098 de 2006',
        'articulo' => 'Arts. 61 y 64 - Adopcion',
        'texto' => 'Art. 61 define la adopcion como medida de proteccion. Art. 64 establece sus efectos juridicos, incluyendo derechos, obligaciones y parentesco civil.',
        'uso' => 'Aplica a DEC-014.',
    ],
    [
        'norma' => 'Ley 54 de 1990',
        'articulo' => 'Arts. 1 y 2 - Union marital de hecho',
        'texto' => 'Art. 1 define la union marital de hecho. Art. 2 regula la presuncion de sociedad patrimonial entre companeros permanentes bajo los supuestos legales.',
        'uso' => 'Aplica a DEC-016.',
    ],
    [
        'norma' => 'Ley 1581 de 2012',
        'articulo' => 'Arts. 4, 5, 9 y 17 - Datos personales',
        'texto' => 'Art. 4 fija principios; art. 5 trata datos sensibles; art. 9 exige autorizacion; art. 17 establece deberes del responsable del tratamiento.',
        'uso' => 'Aplica a DEC-017 y DEC-018; util para censos, genogramas, datos de familiares y anexos.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 5, 17, 24 y 45 - S.A.S. y acuerdos',
        'texto' => 'Art. 5 orienta estatutos; art. 17 permite reglas internas; art. 24 regula acuerdos de accionistas; art. 45 remite a normas societarias aplicables.',
        'uso' => 'Aplica a DEC-011, DEC-012, DEC-019 y DEC-020.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 19, 20 y 21 - Formalizacion',
        'texto' => 'Regulan reuniones no presenciales, decisiones por comunicacion escrita y actas.',
        'uso' => 'Aplica a todo CAT-02 para aprobar alcance, adhesion, vigencia y revisiones en un documento verificable.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 28, 31, 32, 33 y 34',
        'texto' => 'Recomienda consejo de familia, protocolo, roles de familiares y manejo de operaciones familia-sociedad.',
        'uso' => 'Marco consultivo para ordenar familia, negocio y propiedad.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Jurisprudencia y doctrina',
        'articulo' => 'C-075 de 2021, C-192 de 2023, C-058 de 2018 y doctrina Supersociedades',
        'texto' => 'Aporta criterios sobre parentesco, adopcion, igualdad familiar, formas de familia y naturaleza preventiva del protocolo.',
        'uso' => 'Soporte interpretativo para explicar que el alcance no es caprichoso, sino una regla interna con base juridica.',
    ],
];
$cat03Academy = [
    'DEC-021' => [
        'decision' => 'Definir si la familia debe conservar el control mayoritario directo o indirecto de las sociedades familiares.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 17 (organizacion interna), art. 24 (acuerdos de accionistas), art. 30 (reformas estatutarias), art. 31 (transformacion) y art. 40 (conflictos societarios).',
        'ejemplo' => 'La familia mantendra directa o indirectamente mas del 50% de los derechos de voto de las sociedades estrategicas, salvo decision extraordinaria aprobada por la asamblea y el consejo de familia.',
        'claro' => 'Debe quedar claro: porcentaje de control, sociedades estrategicas, si el control es economico o politico, organo que autoriza excepciones y soportes.',
        'precedente' => 'Guia Colombiana, Medidas 31 y 32: el protocolo debe ordenar la relacion familia-propiedad-empresa y diferenciar roles de accionistas y administradores.',
    ],
    'DEC-022' => [
        'decision' => 'Definir el porcentaje minimo de propiedad o voto que la familia debe conservar para evitar perdida de control.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 (acuerdos sobre compra/venta, preferencias, restricciones, voto y otros asuntos licitos) y art. 11 (voto singular o multiple). Codigo de Comercio art. 379 (derechos de cada accion).',
        'ejemplo' => 'La familia no podra reducir su participacion agregada por debajo del 70% de las acciones ordinarias con voto, salvo autorizacion unificada de la asamblea familiar y organo societario competente.',
        'claro' => 'Debe quedar claro: porcentaje minimo, base de calculo, acciones incluidas, excepciones y mecanismo de recomposicion.',
        'precedente' => 'Supersociedades ha reconocido la amplitud de pactos de accionistas en S.A.S. siempre que sean licitos y compatibles con estatutos.',
    ],
    'DEC-023' => [
        'decision' => 'Definir si la propiedad se organiza por personas individualmente o por ramas familiares para equilibrar continuidad y representacion.',
        'fundamento' => 'Articulos guia: Constitucion art. 58 (propiedad privada), Ley 1258/2008 art. 24 (acuerdos de accionistas) y art. 17 (reglas internas). Ley 222/1995 art. 21 (actas de decisiones).',
        'ejemplo' => 'La propiedad podra mantenerse por ramas familiares mediante vehiculos o acuerdos internos, sin desconocer la titularidad legal de cada accionista registrada en libros.',
        'claro' => 'Debe quedar claro: que es una rama, quien la representa, como vota, como se resuelven desacuerdos y que pasa con nuevas generaciones.',
        'precedente' => 'La regla por ramas no reemplaza el libro de accionistas ni la titularidad legal; requiere soporte estatutario o acuerdo de accionistas si afecta derechos societarios.',
    ],
    'DEC-024' => [
        'decision' => 'Definir si la transmision patrimonial buscara igualdad entre descendientes o reglas diferenciadas compatibles con ley sucesoral y societaria.',
        'fundamento' => 'Articulos guia: Constitucion art. 42 (igualdad de hijos) y art. 58 (propiedad). Codigo Civil art. 1602 (fuerza obligatoria de contratos validos) cuando existan acuerdos; reglas sucesorales aplicables segun planeacion patrimonial.',
        'ejemplo' => 'La familia procurara igualdad economica entre descendientes, pero podra diferenciar derechos politicos de administracion mediante estatutos, acuerdos o vehiculos de control.',
        'claro' => 'Debe quedar claro: igualdad economica o politica, instrumentos usados, limites sucesorales, proteccion de legitimarios y soporte juridico.',
        'precedente' => 'La igualdad familiar no obliga a identica estructura de voto; las diferencias deben documentarse y respetar normas imperativas.',
    ],
    'DEC-025' => [
        'decision' => 'Definir si un familiar o rama puede acumular un maximo de acciones para evitar concentracion excesiva o bloqueo.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 (restricciones de transferencia y acuerdos licitos), art. 11 (derechos de voto por clase de acciones) y art. 43 (abuso del derecho de voto).',
        'ejemplo' => 'Ningun familiar podra concentrar mas del 35% de los votos familiares sin aprobacion previa y oferta de recomposicion a las demas ramas.',
        'claro' => 'Debe quedar claro: limite, si aplica a acciones o votos, operaciones exceptuadas, sancion interna y mecanismo de correccion.',
        'precedente' => 'El art. 43 de la Ley 1258 permite discutir abuso de voto; por eso conviene prevenir concentraciones que generen bloqueo o perjuicio.',
    ],
    'DEC-026' => [
        'decision' => 'Definir si todos los propietarios familiares tienen iguales derechos politicos o si existiran clases, pactos o voto diferenciado.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 379 (derechos de cada accion), Ley 1258/2008 art. 10 (clases y series de acciones), art. 11 (voto singular o multiple) y art. 24 (acuerdos de voto).',
        'ejemplo' => 'Las acciones ordinarias conservaran un voto por accion; cualquier voto multiple o restriccion politica requerira reforma estatutaria y explicacion previa al grupo familiar.',
        'claro' => 'Debe quedar claro: tipo de accion, derechos economicos, derechos politicos, voto multiple, voto sindicado y aprobaciones requeridas.',
        'precedente' => 'La S.A.S. permite flexibilidad en clases y votos, pero debe expresarse en estatutos o acuerdos claros para evitar disputas.',
    ],
    'DEC-027' => [
        'decision' => 'Definir si se usaran acciones con derechos economicos preferentes pero sin voto, y para que finalidad familiar.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 10 (clases de acciones, incluidas acciones con dividendo preferencial y sin voto) y art. 11 (derechos de votacion por clase). Ley 222/1995 art. 61 (limite para acciones con dividendo preferencial y sin voto, cuando aplique).',
        'ejemplo' => 'La sociedad podra emitir acciones con dividendo preferencial y sin voto para sucesion economica, sin entregar control politico, respetando estatutos y limites legales aplicables.',
        'claro' => 'Debe quedar claro: clase de accion, dividendo, ausencia o limites de voto, derechos excepcionales, porcentaje maximo y reforma requerida.',
        'precedente' => 'Supersociedades ha explicado que en S.A.S. el art. 10 permite crear clases y series de acciones segun necesidades del caso.',
    ],
    'DEC-028' => [
        'decision' => 'Definir si se permitira separar nuda propiedad, usufructo, dividendos y voto sobre acciones familiares.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 410 (usufructo de acciones y reserva de derechos) y art. 412 (derechos del usufructuario salvo estipulacion en contrario). Codigo Civil art. 823 y ss. sobre usufructo, cuando aplique.',
        'ejemplo' => 'El fundador podra reservar usufructo economico o politico sobre acciones transferidas, siempre que el documento indique quien recibe dividendos, quien vota y que derechos conserva el nudo propietario.',
        'claro' => 'Debe quedar claro: titular, usufructuario, dividendos, voto, duracion, reserva expresa de derechos y registro/documento soporte.',
        'precedente' => 'Supersociedades ha citado los arts. 410 y 412 del Codigo de Comercio para precisar efectos del usufructo de acciones.',
    ],
    'DEC-029' => [
        'decision' => 'Definir si un familiar puede ser accionista aunque no trabaje en la empresa, separando propiedad, empleo y administracion.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 22, 23 y 24 (administradores, deberes y responsabilidad). Ley 1258/2008 art. 27 (responsabilidad de administradores en S.A.S.). Guia Colombiana Medida 32 (roles y limites familiares).',
        'ejemplo' => 'Ser accionista no dara derecho automatico a empleo, salario, cargo directivo ni uso de activos; el ingreso laboral exigira perfil, vacante, proceso y aprobacion definidos.',
        'claro' => 'Debe quedar claro: derechos del propietario, requisitos de empleo, remuneracion, evaluacion, conflictos y causal de retiro laboral.',
        'precedente' => 'La guia de gobierno corporativo recomienda separar rol familiar, accionista, empleado y administrador.',
    ],
    'DEC-030' => [
        'decision' => 'Definir si las participaciones se concentraran en una sociedad holding o patrimonial para ordenar control, sucesion y gobierno.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 5 y 17 (constitucion y organizacion de S.A.S.), art. 24 (acuerdos de accionistas), art. 30 (reformas estatutarias) y art. 31 (transformacion). Constitucion art. 58 (propiedad).',
        'ejemplo' => 'La familia evaluara crear una holding patrimonial para concentrar participaciones, coordinar voto, administrar dividendos y facilitar sucesion, previo concepto legal, tributario y societario.',
        'claro' => 'Debe quedar claro: finalidad, activos que entran, gobierno de la holding, efectos tributarios, control, dividendos y salida de accionistas.',
        'precedente' => 'La holding puede ser instrumento de continuidad, pero requiere diseno juridico y tributario especifico, no solo una decision familiar.',
    ],
];
$cat03LegalReferences = [
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 10 y 11 - Clases de acciones y voto',
        'texto' => 'Permiten crear diversas clases y series de acciones y definir voto singular o multiple en estatutos.',
        'uso' => 'Aplica a DEC-026 y DEC-027; tambien a reglas de control y voto.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Art. 24 - Acuerdos de accionistas',
        'texto' => 'Permite pactos sobre compra, venta, preferencia, restricciones de transferencia, voto, representacion y otros asuntos licitos.',
        'uso' => 'Base central de CAT-03: control, porcentaje minimo, ramas, concentracion, adhesion y voto coordinado.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 30, 31, 40 y 43',
        'texto' => 'Regulan reformas estatutarias, transformacion, conflictos societarios y abuso del derecho de voto.',
        'uso' => 'Aplica a cambios de control, holding, bloqueos, concentracion y conflictos entre accionistas.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 379, 381, 410 y 412',
        'texto' => 'Regulan derechos de las acciones, acciones privilegiadas y usufructo de acciones.',
        'uso' => 'Aplica a derechos politicos, acciones preferenciales, voto y separacion entre usufructo/nuda propiedad.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 22, 23, 24 y 61',
        'texto' => 'Define administradores, sus deberes/responsabilidad y reglas sobre acciones con dividendo preferencial y sin voto cuando aplique.',
        'uso' => 'Aplica a propiedad sin empleo, administradores familiares y acciones sin voto.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 31 y 32',
        'texto' => 'Recomienda protocolo de familia y reglas claras de roles, deberes y limites de familiares.',
        'uso' => 'Soporta separar propiedad, empleo, administracion y control familiar.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$cat04Academy = [
    'DEC-031' => [
        'decision' => 'Definir quienes pueden adquirir acciones o participaciones familiares: familiares, ramas, sociedad, holding o terceros autorizados.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 403 (libre negociabilidad con excepciones), art. 407 (derecho de preferencia si esta pactado) y Ley 1258/2008 art. 24 (acuerdos de accionistas sobre compra, venta y restricciones).',
        'ejemplo' => 'Solo podran adquirir acciones los accionistas familiares, la holding familiar o terceros aprobados por la asamblea con concepto previo del consejo de familia.',
        'claro' => 'Debe quedar claro: adquirentes permitidos, adquirentes prohibidos, autorizacion requerida, procedimiento y excepciones.',
        'precedente' => 'Consejo de Estado y Supersociedades han relacionado los arts. 403 y 407 con la negociacion y preferencia accionaria.',
    ],
    'DEC-032' => [
        'decision' => 'Definir si la sociedad, accionistas o ramas familiares tienen derecho preferente para adquirir acciones antes de ofrecerlas a terceros.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 407 (derecho de preferencia en negociacion de acciones nominativas, plazos y condiciones) y art. 403 (libre negociabilidad con excepciones). Ley 1258/2008 art. 24.',
        'ejemplo' => 'Toda venta debera ofrecerse primero a los accionistas familiares y luego a la sociedad o holding; solo si ninguno ejerce preferencia podra ofrecerse a terceros autorizados.',
        'claro' => 'Debe quedar claro: orden de preferencia, plazo, precio, forma de pago, peritos si no hay acuerdo y consecuencia de omitir el procedimiento.',
        'precedente' => 'Oficios de Supersociedades sobre art. 407 explican que la preferencia debe estar pactada y contener plazos y condiciones.',
    ],
    'DEC-033' => [
        'decision' => 'Definir procedimiento para ventas, cesiones o transferencias de acciones entre miembros de la familia.',
        'fundamento' => 'Articulos guia: Codigo de Comercio arts. 403 y 407; Ley 1258/2008 art. 24; Ley 222/1995 art. 21 para actas que documenten autorizaciones.',
        'ejemplo' => 'La transferencia interna exigira oferta escrita, valoracion o precio pactado, verificacion de derecho de preferencia, aprobacion del organo competente y registro en libro de accionistas.',
        'claro' => 'Debe quedar claro: pasos, documentos, precio, forma de pago, aprobaciones, registro y plazo de cierre.',
        'precedente' => 'La transferencia familiar sin procedimiento claro suele generar conflictos por precio, oportunidad y trato entre ramas.',
    ],
    'DEC-034' => [
        'decision' => 'Definir bajo que condiciones puede venderse a terceros externos y que protecciones se exigen para conservar control familiar.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 403 (negociabilidad), art. 407 (preferencia si existe), Ley 1258/2008 art. 24 (restricciones de transferencia y acuerdos licitos) y art. 43 (abuso del derecho de voto).',
        'ejemplo' => 'La venta a terceros solo procedera por necesidad de liquidez, oferta estrategica o salida aprobada, agotando preferencia y con mayoria reforzada.',
        'claro' => 'Debe quedar claro: causales, mayoria, tercero permitido, debida diligencia, confidencialidad y proteccion del control.',
        'precedente' => 'El derecho de preferencia opera como excepcion pactada a la libre negociabilidad; debe estar en estatutos o acuerdo exigible.',
    ],
    'DEC-035' => [
        'decision' => 'Definir si se permiten donaciones de acciones y bajo que condiciones familiares, societarias, tributarias y sucesorales.',
        'fundamento' => 'Articulos guia: Codigo de Comercio arts. 403 y 407 cuando la donacion implique transferencia de acciones; Codigo Civil reglas de donacion y sucesiones; Constitucion art. 58 sobre propiedad.',
        'ejemplo' => 'Las donaciones de acciones a descendientes seran permitidas si respetan restricciones estatutarias, derecho de preferencia aplicable, planeacion sucesoral y firma de adhesion al protocolo.',
        'claro' => 'Debe quedar claro: beneficiarios, autorizaciones, restricciones, efectos sucesorales, impuestos y adhesion al protocolo.',
        'precedente' => 'Aunque la donacion no es venta, puede ser transferencia y debe revisarse frente a estatutos, preferencia y reglas de propiedad familiar.',
    ],
    'DEC-036' => [
        'decision' => 'Definir si las acciones pueden darse en prenda, garantia o gravamen por obligaciones personales o empresariales.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 410 (perfeccionamiento de prenda/usufructo de acciones) y art. 411 (derechos del acreedor prendario requieren pacto expreso). Ley 1676/2013 sobre garantias mobiliarias cuando aplique.',
        'ejemplo' => 'Las acciones familiares no podran darse en prenda por deudas personales sin autorizacion previa; cualquier garantia debera registrarse y limitar derechos politicos del acreedor.',
        'claro' => 'Debe quedar claro: si se permite prenda, obligaciones autorizadas, registro, derechos del acreedor, voto, incumplimiento y liberacion.',
        'precedente' => 'Doctrina societaria resalta que la prenda no transmite por si sola derechos de accionista salvo estipulacion expresa.',
    ],
    'DEC-037' => [
        'decision' => 'Definir el procedimiento de salida voluntaria de un accionista familiar y sus derechos de liquidez.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 (acuerdos de compra/venta, preferencias y forma de salida), Codigo de Comercio art. 407 (preferencia) y art. 396 si la sociedad readquiere acciones.',
        'ejemplo' => 'El accionista que desee retirarse debera notificar por escrito, activar preferencia, someterse a valoracion pactada y aceptar pago segun formula o cronograma aprobado.',
        'claro' => 'Debe quedar claro: aviso, plazo, comprador, valoracion, descuento, forma de pago, garantias y restricciones mientras se cierra.',
        'precedente' => 'La salida voluntaria debe evitar iliquidez y bloqueo; por eso conviene pactar procedimiento antes del conflicto.',
    ],
    'DEC-038' => [
        'decision' => 'Definir hechos que obligan a un accionista a vender: incumplimiento grave, competencia, violacion de confidencialidad, embargo, incapacidad legal o conflicto severo.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 (restricciones y asuntos licitos), art. 40 (conflictos societarios) y art. 43 (abuso de voto). Codigo de Comercio art. 407 si aplica preferencia.',
        'ejemplo' => 'Podra exigirse venta obligatoria por violacion grave del protocolo, competencia no autorizada, divulgacion de informacion reservada o incumplimiento de pactos de accionistas.',
        'claro' => 'Debe quedar claro: causales, debido proceso interno, organo que decide, precio, descuento o penalidad y mecanismo de defensa.',
        'precedente' => 'Las restricciones deben ser proporcionales, licitas, documentadas y compatibles con estatutos para evitar nulidad o litigio.',
    ],
    'DEC-039' => [
        'decision' => 'Definir si existira fondo, reserva o mecanismo financiero para recomprar acciones de familiares que se retiren.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 396 (readquisicion de acciones propias: decision de asamblea, utilidades liquidas o reserva y acciones liberadas) y Ley 1258/2008 art. 24.',
        'ejemplo' => 'La familia evaluara crear reserva anual de liquidez para recompras, sin obligar a la sociedad a readquirir si no cumple requisitos legales y financieros.',
        'claro' => 'Debe quedar claro: fuente de recursos, limite anual, quien compra, requisitos, prioridad de casos y formula de pago.',
        'precedente' => 'Supersociedades y doctrina reiteran que la readquisicion requiere cumplir estrictamente condiciones del art. 396.',
    ],
    'DEC-040' => [
        'decision' => 'Definir que mayoria y procedimiento se exige para vender la totalidad, una unidad estrategica o el control de la empresa familiar.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 (acuerdos de voto y venta), art. 30 (reformas estatutarias), art. 31 (transformacion cuando aplique) y Codigo de Comercio art. 403.',
        'ejemplo' => 'La venta de control requerira mayoria reforzada de accionistas, concepto del consejo de familia, valoracion independiente y periodo de informacion previo.',
        'claro' => 'Debe quedar claro: que se entiende por control, mayoria, informacion previa, asesor externo, precio minimo y destino de recursos.',
        'precedente' => 'Una venta de control cambia continuidad, gobierno y patrimonio; debe tener regla previa para evitar decisiones impulsivas o bloqueos.',
    ],
];
$cat04LegalReferences = [
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 403 y 407 - Negociabilidad y preferencia',
        'texto' => 'Art. 403 parte de libre negociabilidad de acciones con excepciones. Art. 407 regula derecho de preferencia en acciones nominativas cuando se pacta.',
        'uso' => 'Base de adquirentes autorizados, preferencia, transferencia interna y venta a terceros.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Art. 396 - Readquisicion de acciones',
        'texto' => 'Exige decision de asamblea, acciones totalmente liberadas y fondos de utilidades liquidas o reserva destinada a la operacion.',
        'uso' => 'Base del fondo de recompra y salidas de accionistas cuando la sociedad sea compradora.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 410 y 411 - Prenda de acciones',
        'texto' => 'Regulan perfeccionamiento de prenda/usufructo y derechos del acreedor prendario mediante pacto expreso.',
        'uso' => 'Base para permitir o restringir acciones en garantia.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 24, 40 y 43',
        'texto' => 'Art. 24 permite acuerdos de accionistas; art. 40 trata conflictos societarios; art. 43 abuso del derecho de voto.',
        'uso' => 'Base para restricciones, salida obligatoria, venta de control y solucion de conflictos.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Art. 21 - Actas',
        'texto' => 'Permite documentar aprobaciones, ofertas, decisiones de salida, recompras y venta de control.',
        'uso' => 'Base documental para trazabilidad de CAT-04.',
    ],
    [
        'norma' => 'Doctrina Supersociedades / Consejo de Estado',
        'articulo' => 'Preferencia, readquisicion y prenda',
        'texto' => 'La doctrina ha precisado que la preferencia debe estar pactada, la readquisicion exige requisitos estrictos y la prenda no transfiere derechos politicos salvo pacto.',
        'uso' => 'Soporte interpretativo para explicar a la familia que liquidez y salida requieren reglas previas.',
    ],
];
$cat05Academy = [
    'DEC-041' => [
        'decision' => 'Definir si se exigiran capitulaciones antes de que un familiar reciba, adquiera o conserve acciones familiares.',
        'fundamento' => 'Articulos guia: Codigo Civil arts. 1771 a 1780 (capitulaciones matrimoniales), especialmente art. 1771 (definicion) y art. 1773 (limites: no contrariar ley ni buenas costumbres). Constitucion art. 42.',
        'ejemplo' => 'Antes de recibir acciones familiares, el beneficiario debera acreditar capitulaciones o acuerdo patrimonial equivalente que proteja la continuidad accionaria familiar.',
        'claro' => 'Debe quedar claro: momento de exigencia, documento aceptado, excepciones, asesor juridico, consecuencia de no firmar y tratamiento de uniones de hecho.',
        'precedente' => 'Corte Constitucional C-278 de 2014 explica la relevancia del regimen de sociedad conyugal y el haber social cuando no hay capitulaciones.',
    ],
    'DEC-042' => [
        'decision' => 'Definir si las acciones familiares deben mantenerse separadas de la sociedad conyugal o patrimonial.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1781 (haber de la sociedad conyugal) y arts. 1771 a 1780 (capitulaciones). Ley 54/1990 arts. 1 y 2 para sociedad patrimonial entre companeros permanentes.',
        'ejemplo' => 'Las acciones familiares deberan mantenerse como bien propio o excluido del haber comun mediante capitulaciones, acuerdos patrimoniales o instrumentos equivalentes validos.',
        'claro' => 'Debe quedar claro: acciones cubiertas, regimen aplicable, soportes, actualizacion por nuevas adquisiciones y efectos de dividendos o valorizaciones.',
        'precedente' => 'C-278 de 2014 indica que, a falta de capitulaciones, el haber social se integra por reglas del art. 1781 del Codigo Civil.',
    ],
    'DEC-043' => [
        'decision' => 'Definir que informacion debe recibir la pareja antes de suscribir acuerdos patrimoniales, sin exponer informacion reservada indebidamente.',
        'fundamento' => 'Articulos guia: Constitucion art. 15 (intimidad, buen nombre y habeas data) y art. 42 (relaciones familiares). Ley 1581/2012 arts. 4, 5, 9 y 17 sobre datos personales.',
        'ejemplo' => 'La pareja recibira explicacion suficiente sobre alcance patrimonial, confidencialidad y derechos que no adquiere, sin acceder a informacion reservada de sociedades salvo autorizacion.',
        'claro' => 'Debe quedar claro: informacion minima, responsable de explicarla, confidencialidad, autorizacion de datos y soporte de comprension.',
        'precedente' => 'La transparencia previa reduce riesgo de nulidad, presiones indebidas o conflictos por falta de informacion.',
    ],
    'DEC-044' => [
        'decision' => 'Definir si conyuges o companeros permanentes pueden adquirir acciones directamente y bajo que condiciones.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 (restricciones, compra/venta y asuntos licitos), Codigo de Comercio arts. 403 y 407 (negociabilidad y preferencia), Constitucion art. 42.',
        'ejemplo' => 'Conyuges o companeros permanentes solo podran adquirir acciones si cumplen derecho de preferencia, adhesion al protocolo, confidencialidad y aprobacion del organo competente.',
        'claro' => 'Debe quedar claro: si pueden ser accionistas, procedimiento, derechos politicos, confidencialidad, salida en caso de separacion y restricciones.',
        'precedente' => 'La calidad de pareja no equivale automaticamente a calidad de accionista; los derechos societarios dependen de titularidad y estatutos.',
    ],
    'DEC-045' => [
        'decision' => 'Definir si conyuges o companeros permanentes pueden trabajar en empresas familiares y bajo que reglas.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 22, 23 y 24 si ejercen administracion. Codigo Sustantivo del Trabajo y politicas internas cuando exista relacion laboral. Guia Colombiana Medida 32 sobre roles y limites familiares.',
        'ejemplo' => 'Las parejas podran trabajar solo si existe vacante, perfil, proceso de seleccion, contrato formal, evaluacion y manejo de conflicto de interes.',
        'claro' => 'Debe quedar claro: requisitos de ingreso, jefe directo, remuneracion, evaluacion, conflictos, confidencialidad y salida por separacion.',
        'precedente' => 'La separacion familia-empresa exige que el empleo no sea beneficio automatico por relacion afectiva.',
    ],
    'DEC-046' => [
        'decision' => 'Definir si conyuges o companeros pueden participar en asamblea familiar, consejo o actividades, y con que voz o voto.',
        'fundamento' => 'Articulos guia: Constitucion art. 42; Codigo Civil art. 47 (afinidad); Ley 54/1990 arts. 1 y 2. Guia Colombiana Medidas 28, 31 y 32 sobre organos familiares y roles.',
        'ejemplo' => 'Las parejas podran asistir a actividades de integracion y formacion, con voz pero sin voto en decisiones de propiedad, salvo que sean accionistas.',
        'claro' => 'Debe quedar claro: espacios, voz, voto, confidencialidad, acceso a informacion y efecto de separacion.',
        'precedente' => 'El protocolo puede invitar parejas a la vida familiar sin convertirlas automaticamente en decisores de propiedad.',
    ],
    'DEC-047' => [
        'decision' => 'Definir que ocurre si acciones o derechos economicos terminan adjudicados a una expareja por divorcio, separacion o liquidacion patrimonial.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1781 (haber social), Ley 54/1990 arts. 2 y 4, Codigo de Comercio arts. 403 y 407, Ley 1258/2008 art. 24.',
        'ejemplo' => 'Si una expareja recibe acciones, debera ofrecerlas primero a la familia o sociedad segun derecho de preferencia y firmar confidencialidad mientras conserve cualquier derecho.',
        'claro' => 'Debe quedar claro: obligacion de venta, preferencia, precio, plazo, confidencialidad, derechos mientras se ejecuta la recompra y solucion de conflicto.',
        'precedente' => 'El derecho de preferencia suele operar frente a actos de negociacion; las adjudicaciones por liquidacion deben revisarse con soporte estatutario y judicial/notarial.',
    ],
    'DEC-048' => [
        'decision' => 'Definir si existe obligacion o mecanismo de recompra de acciones adjudicadas a una expareja.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 396 si recompra la sociedad; arts. 403 y 407 si compran accionistas; Ley 1258/2008 art. 24 para pactar salida y restricciones.',
        'ejemplo' => 'La familia tendra opcion preferente de recompra de acciones adjudicadas a expareja, con valoracion independiente y pago en plazo pactado.',
        'claro' => 'Debe quedar claro: comprador, precio, plazo, forma de pago, garantias, restricciones de voto y consecuencias de no vender.',
        'precedente' => 'La recompra debe respetar requisitos legales si la hace la sociedad; si la hacen accionistas, debe seguir pactos y preferencia.',
    ],
    'DEC-049' => [
        'decision' => 'Definir deberes de confidencialidad de conyuges, companeros permanentes y exparejas con acceso a informacion familiar o empresarial.',
        'fundamento' => 'Articulos guia: Constitucion art. 15; Ley 1581/2012 arts. 4, 5, 9 y 17; Ley 222/1995 art. 23 si la persona ejerce administracion o maneja informacion societaria.',
        'ejemplo' => 'Toda pareja con acceso a informacion no publica debera firmar acuerdo de confidencialidad, tratamiento de datos y devolucion o destruccion de informacion al terminar su participacion.',
        'claro' => 'Debe quedar claro: informacion reservada, duracion, sanciones, tratamiento de datos, voceria y obligaciones tras separacion.',
        'precedente' => 'La confidencialidad protege intimidad familiar, secretos empresariales, datos personales y reputacion.',
    ],
    'DEC-050' => [
        'decision' => 'Definir consecuencias si un familiar o su pareja se niega a firmar acuerdos patrimoniales, adhesiones o confidencialidad exigidos.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1602 (contrato validamente celebrado obliga a las partes), Ley 1258/2008 art. 24 (acuerdos de accionistas), Codigo Civil arts. 1771 a 1780 si son capitulaciones.',
        'ejemplo' => 'La negativa injustificada podra suspender beneficios familiares, impedir adquisicion de acciones o activar evaluacion del consejo de familia, sin afectar derechos adquiridos sin debido soporte legal.',
        'claro' => 'Debe quedar claro: acuerdos exigidos, oportunidad, consecuencia, debido proceso, excepciones y respeto por derechos adquiridos.',
        'precedente' => 'Las consecuencias deben ser proporcionales y documentadas; el protocolo no debe imponer sanciones contrarias a ley o derechos adquiridos.',
    ],
];
$cat05LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Arts. 15 y 42',
        'texto' => 'Art. 15 protege intimidad y datos; art. 42 regula familia, pareja, igualdad y proteccion familiar.',
        'uso' => 'Base para informacion a parejas, confidencialidad, participacion y trato de conyuges/companeros.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Arts. 1771 a 1780 - Capitulaciones',
        'texto' => 'Regulan acuerdos patrimoniales previos al matrimonio y sus limites.',
        'uso' => 'Base para exigir capitulaciones o acuerdos patrimoniales antes de recibir acciones.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Art. 1781 - Haber social',
        'texto' => 'Define que bienes integran la sociedad conyugal a falta de acuerdos validos.',
        'uso' => 'Base para separar acciones, dividendos, frutos y valorizaciones con asesoria juridica.',
    ],
    [
        'norma' => 'Ley 54 de 1990 y Ley 979 de 2005',
        'articulo' => 'Arts. 1, 2 y 4 - Union marital y prueba',
        'texto' => 'Define union marital, sociedad patrimonial y mecanismos de declaracion/prueba.',
        'uso' => 'Base para companeros permanentes y acuerdos patrimoniales equivalentes.',
    ],
    [
        'norma' => 'Ley 1258 de 2008 / Codigo de Comercio',
        'articulo' => 'Ley 1258 art. 24; C.Co. arts. 396, 403 y 407',
        'texto' => 'Regulan acuerdos de accionistas, recompra, negociabilidad y derecho de preferencia.',
        'uso' => 'Base si parejas o exparejas adquieren acciones o deben salir.',
    ],
    [
        'norma' => 'Corte Constitucional',
        'articulo' => 'C-278 de 2014',
        'texto' => 'Explica el regimen de sociedad conyugal y el art. 1781 cuando no hay capitulaciones.',
        'uso' => 'Soporte pedagogico para explicar por que no es capricho pedir reglas patrimoniales antes del conflicto.',
    ],
];
$cat06Academy = [
    'DEC-051' => [
        'decision' => 'Definir si los accionistas deben mantener testamento actualizado y coherente con protocolo, estatutos y acuerdos de accionistas.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1055 (definicion de testamento), art. 1059 (acto de una sola persona), reglas de asignaciones forzosas y Ley 1258/2008 art. 24 si hay pactos de accionistas.',
        'ejemplo' => 'Todo accionista familiar debera revisar su testamento al menos cada dos anos o ante nacimiento, matrimonio, divorcio, adquisicion de acciones o reforma del protocolo.',
        'claro' => 'Debe quedar claro: periodicidad, responsable, compatibilidad con estatutos, legados, herederos, restricciones y confidencialidad del contenido.',
        'precedente' => 'El testamento organiza voluntad patrimonial, pero no puede desconocer normas imperativas sucesorales ni pactos societarios validos.',
    ],
    'DEC-052' => [
        'decision' => 'Definir si los herederos recibiran acciones directamente o su equivalente economico para proteger continuidad y control.',
        'fundamento' => 'Articulos guia: Constitucion art. 58 (propiedad), Codigo Civil art. 1055, Ley 1258/2008 art. 24 (acuerdos sobre acciones), Codigo de Comercio arts. 403 y 407 si hay transferencia o preferencia.',
        'ejemplo' => 'Los herederos podran recibir equivalente economico cuando su ingreso como accionistas afecte control, confidencialidad o gobierno familiar, segun valoracion pactada.',
        'claro' => 'Debe quedar claro: quien recibe acciones, quien dinero, formula de valoracion, plazo de pago, derechos mientras se liquida la sucesion y soporte.',
        'precedente' => 'La planeacion sucesoral debe armonizar igualdad patrimonial, control societario y restricciones de transferencia.',
    ],
    'DEC-053' => [
        'decision' => 'Definir si se permite indivision hereditaria sobre acciones o si debe nombrarse representante y promover particion.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 378 (indivisibilidad de acciones y representante comun cuando una accion pertenezca a varias personas) y art. 379 (derechos de accion). Ley 222/1995 art. 21 para actas.',
        'ejemplo' => 'Si varias personas heredan una misma participacion, deberan designar representante comun en 30 dias y acordar particion o vehiculo de administracion.',
        'claro' => 'Debe quedar claro: representante, voto, dividendos, plazo de particion, decision si no hay acuerdo y restricciones.',
        'precedente' => 'La indivision sin representante puede paralizar voto, dividendos e informacion societaria.',
    ],
    'DEC-054' => [
        'decision' => 'Definir como se representaran herederos menores y quien administrara sus derechos economicos y politicos.',
        'fundamento' => 'Articulos guia: Constitucion art. 44 (derechos prevalentes de ninos), Codigo Civil art. 288 (patria potestad), Ley 1098/2006 sobre proteccion integral y Ley 1258/2008 art. 24 si hay pactos.',
        'ejemplo' => 'Los menores seran representados por quien ejerza patria potestad o guarda, pero el protocolo exigira informacion al consejo de familia y control sobre voto y dividendos.',
        'claro' => 'Debe quedar claro: representante legal, limites, conflictos de interes, autorizaciones, custodia de dividendos y formacion del menor.',
        'precedente' => 'ICBF y normativa civil reconocen que patria potestad incluye representacion y administracion de bienes de hijos no emancipados.',
    ],
    'DEC-055' => [
        'decision' => 'Definir quien ejerce derechos de voto de las acciones mientras se tramita una sucesion.',
        'fundamento' => 'Articulos guia: Codigo de Comercio arts. 378 y 379; Ley 1258/2008 art. 24 sobre acuerdos de voto; Ley 222/1995 arts. 19 a 21 para documentar decisiones transitorias.',
        'ejemplo' => 'Durante la sucesion, los herederos designaran un representante comun o mandatario para ejercer voto conforme a directrices del protocolo y sin bloquear decisiones urgentes.',
        'claro' => 'Debe quedar claro: representante, instrucciones de voto, plazo, reemplazo, rendicion de cuentas y manejo de desacuerdos.',
        'precedente' => 'Sin regla de voto transitorio, una sucesion puede bloquear asambleas, reformas, dividendos o decisiones criticas.',
    ],
    'DEC-056' => [
        'decision' => 'Definir si se designara albacea, mandatario o ejecutor de instrucciones sucesorales para cumplir la voluntad del accionista.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1327 (albaceas o ejecutores testamentarios) y art. 1055 (testamento). Codigo Civil art. 2142 y ss. sobre mandato cuando se use poder o mandato separado.',
        'ejemplo' => 'Cada accionista critico podra designar albacea o mandatario sucesoral con instrucciones sobre acciones, voto, informacion y coordinacion con el consejo de familia.',
        'claro' => 'Debe quedar claro: persona designada, facultades, duracion, remuneracion, reemplazo, limites y documentos que lo soportan.',
        'precedente' => 'El albacea ejecuta voluntad testamentaria; el mandatario requiere poder valido y puede tener limites por muerte o incapacidad segun el instrumento.',
    ],
    'DEC-057' => [
        'decision' => 'Definir quien sustituye temporalmente a fundador, accionista clave o administrador ante incapacidad temporal.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 22, 23 y 24 (administradores y deberes), Ley 1258/2008 art. 27, estatutos sobre suplencias y Codigo Civil sobre mandato si hay apoderado.',
        'ejemplo' => 'Ante incapacidad temporal certificada, asumira el suplente estatutario o delegado previamente definido, con reporte al consejo de familia y limite de facultades extraordinarias.',
        'claro' => 'Debe quedar claro: prueba de incapacidad, suplente, facultades, duracion, reportes, revocatoria y retorno del titular.',
        'precedente' => 'La suplencia debe respetar estatutos y organos societarios; el protocolo no reemplaza designaciones legales.',
    ],
    'DEC-058' => [
        'decision' => 'Definir procedimiento para declarar, administrar y representar incapacidad permanente de un propietario.',
        'fundamento' => 'Articulos guia: Ley 1996/2019 sobre capacidad legal de personas con discapacidad y apoyos; Constitucion art. 13 (igualdad) y art. 47 (proteccion a personas con discapacidad); Ley 1258/2008 art. 24.',
        'ejemplo' => 'La incapacidad o necesidad de apoyo se manejara mediante certificacion y mecanismos legales de apoyo, evitando sustituir derechos sin procedimiento legal.',
        'claro' => 'Debe quedar claro: evaluacion, apoyos, representante o persona de apoyo, voto, dividendos, proteccion de derechos y revision periodica.',
        'precedente' => 'La Ley 1996 cambio el enfoque: no se trata de anular a la persona, sino de establecer apoyos respetando su voluntad y preferencias.',
    ],
    'DEC-059' => [
        'decision' => 'Definir si se contrataran seguros para financiar compra de acciones o liquidez sucesoral ante fallecimiento.',
        'fundamento' => 'Articulos guia: Codigo de Comercio arts. 1036 y ss. sobre contrato de seguro; Ley 1258/2008 art. 24 para acuerdos de compra financiados con seguros; Codigo de Comercio art. 396 si recompra sociedad.',
        'ejemplo' => 'La familia evaluara seguros de vida cruzados o corporativos para financiar recompra de acciones, cubrir impuestos, gastos sucesorales y evitar venta forzada.',
        'claro' => 'Debe quedar claro: asegurado, tomador, beneficiario, valor asegurado, destino de recursos, primas, actualizacion y relacion con valoracion.',
        'precedente' => 'El seguro no reemplaza el acuerdo de compra; solo aporta liquidez si beneficiarios, montos y obligaciones estan alineados.',
    ],
    'DEC-060' => [
        'decision' => 'Definir plan de emergencia si fundador o accionista clave fallece sin completar sucesion o instrucciones.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 19 a 21 (decisiones y actas), Ley 1258/2008 arts. 17, 24 y 40; Codigo de Comercio arts. 378 y 379 sobre representacion y derechos accionarios.',
        'ejemplo' => 'Ante fallecimiento sin plan completo, se activara comite de emergencia, custodia documental, representante transitorio, valoracion inicial y ruta sucesoral con asesor externo.',
        'claro' => 'Debe quedar claro: quien convoca, primeras 72 horas, documentos criticos, voceria, voto transitorio, asesores y decisiones prohibidas.',
        'precedente' => 'Los primeros dias posteriores al fallecimiento suelen definir si hay continuidad ordenada o conflicto por control e informacion.',
    ],
];
$cat06LegalReferences = [
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Arts. 1055 y 1059 - Testamento',
        'texto' => 'Art. 1055 define testamento. Art. 1059 recalca que es acto de una sola persona.',
        'uso' => 'Base de testamentos y plan sucesoral.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Art. 1327 - Albacea',
        'texto' => 'Regula albaceas o ejecutores testamentarios encargados de cumplir la voluntad del testador.',
        'uso' => 'Base para albacea o ejecutor sucesoral.',
    ],
    [
        'norma' => 'Codigo Civil / Ley 1996 de 2019',
        'articulo' => 'Art. 288 y apoyos para discapacidad',
        'texto' => 'Art. 288 trata patria potestad; Ley 1996 regula capacidad legal y apoyos para personas con discapacidad.',
        'uso' => 'Base para herederos menores e incapacidad permanente.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 378, 379 y 396',
        'texto' => 'Regulan indivisibilidad/representacion de acciones, derechos de accion y readquisicion de acciones.',
        'uso' => 'Base para indivision, voto sucesoral, herencia de acciones y recompras.',
    ],
    [
        'norma' => 'Ley 1258 de 2008 / Ley 222 de 1995',
        'articulo' => 'Ley 1258 arts. 17, 24, 27 y 40; Ley 222 arts. 19 a 24',
        'texto' => 'Regulan organizacion, acuerdos de accionistas, administradores, actas y conflictos.',
        'uso' => 'Base para continuidad durante fallecimiento, incapacidad y sucesion.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 1036 y ss. - Seguro',
        'texto' => 'Regulan el contrato de seguro y su estructura basica.',
        'uso' => 'Base para seguro sucesoral y liquidez de recompra.',
    ],
];
$cat07Academy = [
    'DEC-061' => [
        'decision' => 'Definir si existira una asamblea de familia como espacio amplio de informacion, pedagogia, deliberacion y alineacion familiar.',
        'fundamento' => 'Articulos guia: Guia Colombiana de Gobierno Corporativo Medidas 28, 29, 30 y 31 sobre asamblea/consejo de familia y protocolo. Ley 222/1995 arts. 19, 20 y 21 para reuniones, decisiones escritas y actas si alguna decision debe formalizarse. Constitucion art. 42 como marco de proteccion familiar.',
        'ejemplo' => 'La familia creara una asamblea familiar anual integrada por accionistas familiares, descendientes mayores de edad y conyuges invitados sin voto patrimonial, segun reglas de confidencialidad.',
        'claro' => 'Debe quedar claro: quienes asisten, quien tiene voz, quien tiene voto, temas permitidos, informacion que se comparte y diferencia frente a organos societarios.',
        'precedente' => 'La Guia Colombiana recomienda organos de familia para separar familia, propiedad y empresa; este organo orienta, pero no reemplaza asamblea de accionistas ni junta directiva.',
    ],
    'DEC-062' => [
        'decision' => 'Definir periodicidad, convocatoria, agenda minima y reglas de quorum de la asamblea de familia.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28 a 31. Ley 222/1995 arts. 19, 20 y 21 sobre reuniones no presenciales, decisiones escritas y actas. Ley 1258/2008 art. 17 por flexibilidad organizacional si se articula con organos societarios.',
        'ejemplo' => 'La asamblea familiar se reunira ordinariamente una vez al ano y extraordinariamente cuando lo convoque el consejo de familia, dos ramas familiares o accionistas que representen al menos el porcentaje acordado.',
        'claro' => 'Debe quedar claro: frecuencia, convocante, anticipacion, medio, quorum familiar, agenda obligatoria, invitados y soporte de asistencia.',
        'precedente' => 'La trazabilidad de reuniones reduce discusiones sobre quien fue informado, que se decidio y si habia legitimidad para avanzar.',
    ],
    'DEC-063' => [
        'decision' => 'Definir si se creara un consejo de familia como organo permanente de coordinacion, seguimiento y preparacion de decisiones familiares.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medida 28 sobre consejo de familia y Medida 31 sobre protocolo. Ley 1258/2008 art. 24 si sus recomendaciones se conectan con acuerdos de accionistas. Ley 222/1995 art. 21 para actas.',
        'ejemplo' => 'Se creara un consejo de familia encargado de preparar agendas, custodiar el protocolo, hacer seguimiento a acuerdos, canalizar conflictos y recomendar decisiones a los organos competentes.',
        'claro' => 'Debe quedar claro: naturaleza consultiva o decisoria, funciones, limites, relacion con junta/asamblea societaria, responsables y reportes.',
        'precedente' => 'La Guia Colombiana ubica el consejo de familia como una buena practica para ordenar la relacion familia-negocio-propiedad, especialmente antes de conflictos.',
    ],
    'DEC-064' => [
        'decision' => 'Definir numero de integrantes, perfiles, requisitos, incompatibilidades e invitados del consejo de familia.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28, 31 y 32 sobre consejo, protocolo, roles y limites de familiares. Ley 222/1995 arts. 22 y 23 si algun miembro tambien es administrador. Ley 1581/2012 arts. 4, 9 y 17 si maneja datos familiares.',
        'ejemplo' => 'El consejo tendra cinco miembros: un representante por rama, un miembro de nueva generacion y un invitado externo sin voto cuando se requiera criterio tecnico.',
        'claro' => 'Debe quedar claro: numero, requisitos, inhabilidades, edad minima, participacion de conyuges, invitados externos, confidencialidad y suplencias.',
        'precedente' => 'La composicion debe evitar concentracion, captura por una rama o confusion entre familia, empleo y administracion.',
    ],
    'DEC-065' => [
        'decision' => 'Definir si cada rama familiar tendra representacion y como se equilibrara esa representacion con propiedad, generacion y meritocracia.',
        'fundamento' => 'Articulos guia: Constitucion art. 13 (igualdad) y art. 42 (familia). Guia Colombiana Medidas 28, 31 y 32. Ley 1258/2008 art. 24 si la representacion afecta acuerdos de voto o propiedad.',
        'ejemplo' => 'Cada rama familiar tendra al menos un representante en el consejo; si una rama no postula candidato, la silla quedara vacante hasta la siguiente eleccion.',
        'claro' => 'Debe quedar claro: que es una rama, cupos por rama, suplentes, empates, ausencias, representacion de menores o nuevas generaciones y conflictos de interes.',
        'precedente' => 'La representacion por ramas ayuda a legitimidad, pero no debe confundirse con derechos societarios si los porcentajes accionarios son distintos.',
    ],
    'DEC-066' => [
        'decision' => 'Definir mecanismo de eleccion de miembros del consejo: voto familiar, voto por ramas, designacion, postulacion, consenso o sistema mixto.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28 a 31. Ley 222/1995 arts. 19, 20 y 21 para soportar elecciones por reunion, comunicacion escrita y actas. Ley 1258/2008 art. 17 si el mecanismo se conecta con estatutos o gobierno societario.',
        'ejemplo' => 'Los miembros del consejo seran elegidos por la asamblea de familia mediante postulacion previa, hoja de vida, declaracion de conflictos y votacion documentada.',
        'claro' => 'Debe quedar claro: postulacion, electores, votos requeridos, empate, aceptacion del cargo, confidencialidad, conflicto de interes y acta de eleccion.',
        'precedente' => 'Un metodo de eleccion claro evita que el consejo sea percibido como impuesto por fundador, rama mayoritaria o administracion.',
    ],
    'DEC-067' => [
        'decision' => 'Definir periodo, reeleccion, rotacion, causales de retiro y reemplazo de integrantes del consejo de familia.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28, 31 y 32. Ley 222/1995 art. 21 sobre actas y trazabilidad. Codigo Civil art. 1602 para acuerdos validamente celebrados entre quienes los suscriben.',
        'ejemplo' => 'Los miembros tendran periodos de dos anos, con una reeleccion inmediata posible, rotacion por ramas y retiro por incumplimiento de confidencialidad, inasistencia o conflicto de interes no declarado.',
        'claro' => 'Debe quedar claro: duracion, reeleccion, rotacion, renuncia, remocion, suplente, evaluacion y continuidad de temas pendientes.',
        'precedente' => 'La rotacion protege aprendizaje generacional y evita que el organo quede capturado por las mismas personas indefinidamente.',
    ],
    'DEC-068' => [
        'decision' => 'Definir competencias del consejo: que puede decidir, que solo recomienda y que debe elevarse a organos societarios o asesores externos.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 17, 24 y 27; Ley 222/1995 arts. 22, 23 y 24 sobre administradores y deberes; Guia Colombiana Medidas 31 y 32 sobre roles, funciones, deberes y limites.',
        'ejemplo' => 'El consejo decidira agenda familiar, formacion y seguimiento del protocolo; recomendara sobre empleo familiar, liquidez, sucesion y conflictos; no podra reemplazar decisiones de asamblea de accionistas, junta o representante legal.',
        'claro' => 'Debe quedar claro: decisiones propias, recomendaciones, asuntos prohibidos, mayorias internas, escalamiento y organo formal competente.',
        'precedente' => 'La frontera de competencias es critica: un organo familiar no debe ejercer administracion de hecho ni invadir funciones societarias.',
    ],
    'DEC-069' => [
        'decision' => 'Definir como se documentan, aprueban, custodian, consultan y actualizan actas, versiones del protocolo y soportes familiares.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 19, 20 y 21 sobre reuniones, decisiones escritas y actas. Ley 1581/2012 arts. 4, 5, 9 y 17 para datos personales. Ley 1712/2014 como referencia de gestion documental y transparencia cuando aplique a entidades obligadas.',
        'ejemplo' => 'Cada reunion tendra acta con fecha, asistentes, temas, acuerdos, pendientes, responsables y anexos; la custodia estara a cargo de secretaria del consejo con acceso controlado.',
        'claro' => 'Debe quedar claro: formato, responsable, aprobacion, archivo, acceso, confidencialidad, versionamiento, anexos y calendario de seguimiento.',
        'precedente' => 'Sin actas no hay memoria verificable; las decisiones familiares quedan expuestas a interpretaciones, olvidos o discusiones posteriores.',
    ],
    'DEC-070' => [
        'decision' => 'Definir si el consejo tendra presupuesto, asesores, secretaria tecnica, herramientas y recursos para cumplir sus funciones.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28 a 31 sobre funcionamiento de organos familiares. Ley 1258/2008 arts. 17 y 24 si el presupuesto se financia o formaliza mediante reglas societarias/acuerdos. Ley 222/1995 art. 23 si administradores autorizan recursos sociales deben actuar en interes de la sociedad.',
        'ejemplo' => 'El consejo tendra presupuesto anual aprobado, destinado a secretaria tecnica, asesoria juridica, formacion familiar, custodia documental y sesiones de mediacion preventiva.',
        'claro' => 'Debe quedar claro: monto, fuente de pago, aprobacion, gastos autorizados, rendicion de cuentas, topes, asesores y manejo de conflictos.',
        'precedente' => 'Si los recursos salen de la sociedad, debe justificarse interes social y autorizacion del organo competente; si salen de la familia, debe pactarse contribucion y control.',
    ],
];
$cat07LegalReferences = [
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 28, 29, 30 y 31 - Organos de familia y protocolo',
        'texto' => 'Recomienda crear organos de gobierno familiar, definir funcionamiento del consejo/asamblea de familia y adoptar protocolo para ordenar familia, negocio y propiedad.',
        'uso' => 'Base principal de CAT-07: asamblea familiar, consejo, eleccion, reuniones, competencias y seguimiento.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medida 32 - Roles, deberes y limites familiares',
        'texto' => 'Recomienda separar roles de familiares como socios, accionistas, empleados o administradores, y precisar funciones y limites.',
        'uso' => 'Aplica a composicion, competencias, invitados, conflictos y limites del consejo.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 19, 20 y 21 - Reuniones, decisiones escritas y actas',
        'texto' => 'Permiten reuniones no presenciales, decisiones por comunicacion escrita y actas como soporte de lo decidido.',
        'uso' => 'Aplica a periodicidad, elecciones, actas, versionamiento y soporte de decisiones familiares.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 22, 23 y 24 - Administradores, deberes y responsabilidad',
        'texto' => 'Define administradores, deberes de buena fe, lealtad, diligencia, reserva, conflictos de interes y responsabilidad.',
        'uso' => 'Aplica cuando miembros del consejo tambien sean administradores o usen recursos/informacion societaria.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 17, 24 y 27 - Organizacion, acuerdos y administradores',
        'texto' => 'Permite estructura organica flexible en S.A.S., acuerdos de accionistas y remite a responsabilidad de administradores.',
        'uso' => 'Aplica cuando las reglas familiares deban traducirse a estatutos, acuerdos de accionistas o gobierno societario.',
    ],
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Arts. 13, 15, 42 y 58',
        'texto' => 'Soportan igualdad, intimidad/datos, proteccion de la familia y propiedad privada.',
        'uso' => 'Aplica a inclusion familiar, representacion por ramas, informacion reservada y respeto por derechos patrimoniales.',
    ],
    [
        'norma' => 'Ley 1581 de 2012',
        'articulo' => 'Arts. 4, 5, 9 y 17 - Datos personales',
        'texto' => 'Regula principios, datos sensibles, autorizacion y deberes del responsable del tratamiento.',
        'uso' => 'Aplica a censos familiares, actas, datos de miembros, invitados, hojas de vida y archivo del consejo.',
    ],
    [
        'norma' => 'Doctrina y buenas practicas',
        'articulo' => 'Supersociedades, ICGC e IFC sobre gobierno de empresas familiares',
        'texto' => 'Reconocen el valor preventivo de organos familiares, protocolo, consejo, reglas de sucesion, comunicacion y resolucion temprana de conflictos.',
        'uso' => 'Soporte pedagogico para explicar que CAT-07 organiza gobernanza, no impone caprichos familiares.',
    ],
];
$cat08Academy = [
    'DEC-071' => [
        'decision' => 'Definir si las empresas familiares tendran junta directiva aunque la ley o el tipo societario no la exija.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 25 (la S.A.S. no esta obligada a tener junta directiva salvo pacto estatutario) y art. 17 (libertad de organizacion). Codigo de Comercio arts. 434 y 438 para atribuciones e integrantes cuando aplique. Guia Colombiana Medidas 5, 6 y 7 sobre estructura y funcionamiento del organo de administracion.',
        'ejemplo' => 'La sociedad familiar adoptara junta directiva como buena practica, aun si no es obligatoria, para separar propiedad, direccion estrategica y gerencia operativa.',
        'claro' => 'Debe quedar claro: si habra junta, para cuales sociedades, si se reforma estatutos, funciones, tamano, suplencias y relacion con representante legal.',
        'precedente' => 'Supersociedades ha reiterado que en S.A.S. la junta es opcional, pero si se crea debe quedar prevista en estatutos y respetar sus reglas internas.',
    ],
    'DEC-072' => [
        'decision' => 'Definir cuantos miembros independientes tendra la junta y que requisitos deben cumplir para aportar criterio tecnico y neutralidad.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 6, 8, 9 y 10 sobre composicion, perfiles e independencia. Ley 222/1995 arts. 22, 23 y 24: miembros de junta son administradores y responden por deberes de lealtad, diligencia y buena fe. Ley 1258/2008 art. 27.',
        'ejemplo' => 'La junta tendra minimo dos miembros independientes con experiencia financiera, juridica, sectorial o de gobierno corporativo, sin vinculos laborales, comerciales o familiares relevantes.',
        'claro' => 'Debe quedar claro: numero, criterios de independencia, inhabilidades, honorarios, periodo, confidencialidad y declaracion de conflictos.',
        'precedente' => 'La independencia no es decorativa: sirve para elevar calidad de decision, controlar conflictos y proteger el interes social.',
    ],
    'DEC-073' => [
        'decision' => 'Definir cuantos cupos de junta pueden ocupar familiares y como evitar que la junta se convierta en una reunion familiar sin control tecnico.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 22, 23 y 24; Ley 1258/2008 arts. 17, 25 y 27. Codigo de Comercio art. 435 como referencia de cuidado sobre mayorias familiares en sociedades donde aplique. Guia Colombiana Medidas 6, 8 y 32.',
        'ejemplo' => 'Los familiares podran ocupar maximo dos cupos de junta, siempre que cumplan perfil y declaren conflictos; los demas cupos se reservaran para independientes o expertos.',
        'claro' => 'Debe quedar claro: cupos familiares, requisitos, si representan ramas o competencias, limites por parentesco, conflictos y reemplazos.',
        'precedente' => 'Separar cupos familiares de cupos tecnicos reduce captura de la administracion y protege decisiones en interes de la sociedad.',
    ],
    'DEC-074' => [
        'decision' => 'Definir formacion, experiencia, disponibilidad, etica e idoneidad minima para pertenecer a junta directiva.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 (diligencia de buen hombre de negocios, buena fe y lealtad) y art. 24 (responsabilidad). Ley 1258/2008 art. 27. Guia Colombiana Medidas 7, 8, 9 y 10 sobre perfiles, induccion, capacitacion y evaluacion.',
        'ejemplo' => 'Todo miembro de junta debera acreditar experiencia empresarial o profesional relevante, disponibilidad para sesiones, formacion basica en estados financieros, riesgos, estrategia y deberes de administrador.',
        'claro' => 'Debe quedar claro: perfil, experiencia, documentos, induccion, capacitacion anual, conflicto de interes, confidencialidad y causales de retiro.',
        'precedente' => 'La responsabilidad de administradores exige que el cargo no sea simbolico; quien acepta debe poder entender, preguntar y decidir informadamente.',
    ],
    'DEC-075' => [
        'decision' => 'Definir si el presidente de junta puede ser simultaneamente gerente o representante legal, o si deben separarse esos roles.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 22, 23 y 24 sobre administradores, deberes y responsabilidad. Ley 1258/2008 arts. 17, 26 y 27 sobre organizacion y administracion/representacion. Guia Colombiana Medidas 6, 14 y 32 sobre roles, control y separacion de funciones.',
        'ejemplo' => 'La presidencia de junta y la gerencia no podran estar en la misma persona, salvo etapa transitoria aprobada con plazo, controles y rendicion especial de cuentas.',
        'claro' => 'Debe quedar claro: si se permite acumulacion, por cuanto tiempo, controles, quien evalua al gerente, conflictos y reemplazo.',
        'precedente' => 'Separar direccion estrategica y ejecucion operativa mejora supervision; si se acumulan cargos, deben existir controles compensatorios.',
    ],
    'DEC-076' => [
        'decision' => 'Definir periodo, rotacion, reeleccion, suplencias y remocion de miembros de junta.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 17, 23 y 25 sobre organizacion, eleccion de cuerpos colegiados y junta directiva. Codigo de Comercio arts. 197 y 436 como referencias de cuociente electoral cuando aplique; art. 434 sobre integrantes. Ley 222/1995 art. 21 para actas.',
        'ejemplo' => 'Los miembros de junta tendran periodos de dos anos, con reeleccion limitada, evaluacion previa y rotacion escalonada para conservar memoria institucional.',
        'claro' => 'Debe quedar claro: periodo, reeleccion, suplentes, vacancias, remocion, empalme, acta de nombramiento e inscripcion cuando corresponda.',
        'precedente' => 'La rotacion ordenada evita dependencia de una sola persona y permite renovacion sin perder continuidad.',
    ],
    'DEC-077' => [
        'decision' => 'Definir como se evaluara anualmente el funcionamiento de la junta, sus miembros, comites y relacion con gerencia.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 10, 11 y 12 sobre evaluacion y funcionamiento de junta. Ley 222/1995 art. 23 exige diligencia y art. 24 responsabilidad. Ley 1258/2008 art. 27.',
        'ejemplo' => 'La junta tendra autoevaluacion anual con matriz de asistencia, preparacion, calidad de debate, seguimiento de decisiones, manejo de conflictos y aporte individual.',
        'claro' => 'Debe quedar claro: metodologia, responsable, frecuencia, indicadores, retroalimentacion, plan de mejora y consecuencia de bajo desempeno.',
        'precedente' => 'Evaluar junta no es castigo; es evidencia de diligencia y mejora continua del organo de administracion.',
    ],
    'DEC-078' => [
        'decision' => 'Definir si se crearan comites especializados de auditoria, riesgos, inversiones, remuneracion, sucesion u otros, y que alcance tendran.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 13, 14, 15, 16 y 17 sobre apoyo a la junta, arquitectura de control, riesgos, auditoria y revelacion. Ley 222/1995 arts. 23 y 45; Ley 1258/2008 arts. 17 y 25.',
        'ejemplo' => 'La junta creara comite de auditoria y riesgos cuando la empresa supere el tamano definido; para inversiones o sucesion podra crear comites temporales con mandato escrito.',
        'claro' => 'Debe quedar claro: comites, integrantes, funciones, si deciden o recomiendan, periodicidad, informes, presupuesto y limites frente a la junta.',
        'precedente' => 'Los comites ayudan a profundizar temas tecnicos, pero no sustituyen la responsabilidad de la junta ni de los administradores.',
    ],
    'DEC-079' => [
        'decision' => 'Definir si la gerencia puede estar a cargo de una persona no familiar y bajo que criterios de seleccion, evaluacion y control.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 17, 26 y 27; Ley 222/1995 arts. 22, 23, 24 y 45. Guia Colombiana Medidas 6, 18 y 32 sobre administracion profesional, roles familiares y rendicion de cuentas.',
        'ejemplo' => 'La empresa podra nombrar gerente externo cuando el perfil requerido no este disponible en la familia o cuando la junta estime que la profesionalizacion protege mejor la continuidad.',
        'claro' => 'Debe quedar claro: perfil, proceso de seleccion, autoridad, remuneracion, evaluacion, confidencialidad, metas, salida y relacion con familiares accionistas.',
        'precedente' => 'Profesionalizar gerencia no excluye a la familia; permite que la familia gobierne como propietaria sin depender de parentesco para operar.',
    ],
    'DEC-080' => [
        'decision' => 'Definir que informacion debe presentar la administracion a propietarios y junta, con que periodicidad y bajo que nivel de reserva.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 45 sobre rendicion de cuentas, arts. 46 y 47 sobre estados financieros e informe de gestion; Ley 222 arts. 23 y 24 sobre deberes y responsabilidad. Codigo de Comercio art. 443 sobre cuentas del gerente cuando aplique. Guia Colombiana Medidas 18 a 24 sobre revelacion de informacion, control y transparencia.',
        'ejemplo' => 'La administracion presentara trimestralmente tablero financiero, flujo de caja, endeudamiento, riesgos, cumplimiento de presupuesto, avances estrategicos y decisiones que requieran autorizacion.',
        'claro' => 'Debe quedar claro: informes, periodicidad, destinatarios, reserva, indicadores, soportes, responsable, formato y consecuencias de no reportar.',
        'precedente' => 'La rendicion de cuentas documentada protege a propietarios y administradores; permite seguimiento sin convertir a la familia en gerente de hecho.',
    ],
];
$cat08LegalReferences = [
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 17, 25, 26 y 27 - Organizacion, junta y administradores S.A.S.',
        'texto' => 'Art. 17 permite estructura organica flexible; art. 25 indica que la S.A.S. no esta obligada a tener junta salvo estatutos; arts. 26 y 27 tratan representacion y responsabilidad de administradores.',
        'uso' => 'Base de CAT-08 para decidir junta voluntaria, gerencia, separacion de roles y responsabilidad.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 23 y 24 - Eleccion de cuerpos colegiados y acuerdos',
        'texto' => 'Orientan eleccion de cuerpos colegiados y acuerdos de accionistas sobre asuntos licitos, voto, representacion y restricciones.',
        'uso' => 'Aplica a eleccion de junta, cupos, acuerdos de voto y reglas de gobierno entre propietarios.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 22, 23 y 24 - Administradores, deberes y responsabilidad',
        'texto' => 'Define administradores y sus deberes de buena fe, lealtad, diligencia, reserva, manejo de conflictos y responsabilidad.',
        'uso' => 'Aplica a miembros de junta, representantes legales, gerentes y familiares administradores.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 45, 46 y 47 - Rendicion, estados financieros e informe de gestion',
        'texto' => 'Regulan rendicion de cuentas de administradores, presentacion de estados financieros e informe de gestion.',
        'uso' => 'Base de DEC-080 y de la obligacion de reportar a propietarios y organos competentes.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 420, 434, 435, 438 y 443',
        'texto' => 'Regulan funciones del maximo organo, junta directiva, composicion, atribuciones y rendicion de cuentas del gerente cuando aplique.',
        'uso' => 'Referencia para competencias, integrantes, limites familiares, funciones de junta y gerente.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 197 y 436 - Eleccion por cuociente electoral',
        'texto' => 'Regulan eleccion por cuociente electoral en cuerpos colegiados cuando corresponda segun tipo societario o estatutos.',
        'uso' => 'Aplica a eleccion, periodos, representacion y rotacion de miembros de junta.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 5 a 17 - Administradores, junta, comites y control',
        'texto' => 'Recomienda estructura, composicion, independencia, funcionamiento, evaluacion, comites y arquitectura de control.',
        'uso' => 'Marco consultivo principal para profesionalizar la administracion empresarial.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 18 a 24 y 32 - Informacion, transparencia y roles familiares',
        'texto' => 'Recomienda revelacion de informacion, control, transparencia y separacion de roles familiares frente a la empresa.',
        'uso' => 'Aplica a rendicion de cuentas, gerencia externa y limites de participacion familiar.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Doctrina Supersociedades',
        'articulo' => 'Junta directiva en S.A.S. y deberes de administradores',
        'texto' => 'La doctrina reitera que la junta en S.A.S. es opcional salvo estatutos, y que miembros de junta y representantes responden como administradores.',
        'uso' => 'Soporte pedagogico para explicar que una junta voluntaria debe disenar funciones, limites y responsabilidad real.',
    ],
];
$cat09Academy = [
    'DEC-081' => [
        'decision' => 'Definir que decisiones se aprueban por mayoria simple, mayoria calificada o unanimidad, distinguiendo decisiones familiares, societarias y empresariales.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 17 y 24 permiten reglas internas y acuerdos de accionistas; art. 30 sobre reformas estatutarias. Ley 222/1995 arts. 19, 20 y 21 para reuniones, decisiones escritas y actas. Codigo de Comercio art. 420 sobre funciones del maximo organo cuando aplique.',
        'ejemplo' => 'Las decisiones ordinarias se aprobaran por mayoria simple; venta de control, endeudamiento extraordinario, reforma estatutaria o ingreso de terceros requeriran mayoria calificada o unanimidad segun matriz de asuntos reservados.',
        'claro' => 'Debe quedar claro: lista de decisiones, organo competente, mayoria exigida, quorum, excepciones, soporte documental y consecuencia de aprobar sin cumplir la regla.',
        'precedente' => 'Las mayorias reforzadas son validas como mecanismo preventivo si no contradicen ley, estatutos ni derechos inderogables.',
    ],
    'DEC-082' => [
        'decision' => 'Definir si los fundadores conservaran facultades especiales, cuales son, por cuanto tiempo y bajo que limite.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 10 y 11 si se estructuran acciones con derechos especiales o voto diferenciado; arts. 17 y 24 para reglas internas y acuerdos; art. 43 sobre abuso del derecho de voto. Codigo Civil art. 1602 para acuerdos validos entre partes.',
        'ejemplo' => 'Los fundadores conservaran facultad consultiva y veto transitorio sobre venta de control durante cinco anos o hasta retiro definitivo, siempre documentado y sin impedir decisiones necesarias para la sociedad.',
        'claro' => 'Debe quedar claro: facultades, duracion, causales de terminacion, si son personales o transferibles, asuntos cubiertos, conflicto de interes y mecanismo de levantamiento.',
        'precedente' => 'Las facultades especiales ayudan a transicion generacional, pero deben tener limite para no bloquear indefinidamente el gobierno.',
    ],
    'DEC-083' => [
        'decision' => 'Definir si existiran derechos de veto, quienes los tendran y para que asuntos especificos.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 permite acuerdos de voto y otros asuntos licitos; art. 43 regula abuso del derecho de voto, incluso de mayoria, minoria o paridad. Codigo de Comercio art. 420 y estatutos sobre competencias indelegables.',
        'ejemplo' => 'Existira veto solo para venta de control, gravamen de activos esenciales, endeudamiento por encima del limite y cambio sustancial de actividad; el veto debera motivarse por escrito.',
        'claro' => 'Debe quedar claro: titulares, asuntos vetables, plazo para ejercerlo, motivacion, abuso, revision por asesor externo y mecanismo si el veto bloquea la continuidad.',
        'precedente' => 'El veto sin limites puede convertirse en abuso o bloqueo; por eso debe ser excepcional, motivado y revisable.',
    ],
    'DEC-084' => [
        'decision' => 'Definir el limite de endeudamiento que la administracion puede aprobar sin autorizacion superior.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 22, 23 y 24 sobre administradores, diligencia, interes social y responsabilidad. Codigo de Comercio arts. 434 y 438 sobre atribuciones de junta cuando aplique. Ley 1258/2008 arts. 17, 25, 26 y 27.',
        'ejemplo' => 'La gerencia podra aprobar endeudamiento ordinario hasta el monto equivalente al 10% de ingresos anuales; montos superiores requeriran junta y, si comprometen activos esenciales, aprobacion de accionistas.',
        'claro' => 'Debe quedar claro: monto, indicador financiero, tipo de deuda, garantias, plazo, moneda, renovaciones, excepciones urgentes e informes posteriores.',
        'precedente' => 'Un limite financiero evita que la administracion comprometa liquidez o patrimonio sin control de propietarios.',
    ],
    'DEC-085' => [
        'decision' => 'Definir que inversiones relevantes requieren autorizacion de junta, accionistas o consejo familiar previo.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 exige actuar con diligencia y en interes de la sociedad; Codigo de Comercio art. 438 sobre atribuciones de junta; Codigo de Comercio art. 420 sobre funciones del maximo organo; Ley 1258/2008 arts. 17 y 24.',
        'ejemplo' => 'Toda inversion superior al umbral aprobado, inversion fuera del giro ordinario, adquisicion de inmuebles o participacion en negocios no relacionados requerira caso de negocio y aprobacion de junta.',
        'claro' => 'Debe quedar claro: umbral, tipo de inversion, informacion minima, evaluacion de riesgo, aprobador, seguimiento y regla para desinversion.',
        'precedente' => 'La matriz de inversiones protege a la sociedad frente a decisiones impulsivas, conflictos de interes o proyectos personales financiados por la empresa.',
    ],
    'DEC-086' => [
        'decision' => 'Definir quien autoriza la venta, hipoteca, prenda, fiducia, leasing o gravamen de activos esenciales o estrategicos.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 420 y estatutos sobre funciones del maximo organo; Ley 222/1995 arts. 23 y 24 por deberes y responsabilidad de administradores; Ley 1258/2008 arts. 17, 24 y 30 si requiere acuerdo o reforma estatutaria.',
        'ejemplo' => 'La venta o gravamen de activos esenciales requerira concepto financiero, aval juridico, aprobacion de junta y autorizacion de accionistas con mayoria reforzada.',
        'claro' => 'Debe quedar claro: que es activo esencial, umbral, tasacion, organo competente, mayoria, destino de recursos y restricciones por conflicto.',
        'precedente' => 'Los activos estrategicos suelen sostener continuidad y credito; no deben comprometerse solo por decision operativa.',
    ],
    'DEC-087' => [
        'decision' => 'Definir quien aprueba crear, adquirir, vender o participar en nuevas sociedades, filiales, vehiculos o negocios conjuntos.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 5, 17, 24, 30 y 31 sobre estatutos, organizacion, acuerdos, reformas y transformacion cuando aplique. Ley 222/1995 art. 23 sobre diligencia, conflictos y oportunidad de negocio. Codigo de Comercio art. 420.',
        'ejemplo' => 'La creacion o compra de sociedades requerira estudio legal, tributario y financiero, declaracion de beneficiarios reales, aprobacion de junta y autorizacion accionaria si supera el umbral.',
        'claro' => 'Debe quedar claro: tipo de vehiculo, finalidad, capital, control, administradores, riesgos, vinculados, fuente de recursos y salida.',
        'precedente' => 'Nuevas sociedades pueden ordenar crecimiento, pero tambien ocultar riesgos, conflictos o endeudamiento si no tienen aprobacion previa.',
    ],
    'DEC-088' => [
        'decision' => 'Definir si la empresa puede garantizar obligaciones de familiares, accionistas, administradores o empresas relacionadas.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 numeral 7 sobre conflictos de interes y competencia, art. 24 responsabilidad; Guia Supersociedades de conflictos de intereses; Ley 1258/2008 art. 43 sobre abuso del derecho. Codigo de Comercio art. 420 cuando el maximo organo deba autorizar.',
        'ejemplo' => 'La empresa no garantizara obligaciones personales de familiares; garantias a vinculados solo se permitiran si existe interes social demostrable, aprobacion del organo competente y abstencion de conflictuados.',
        'claro' => 'Debe quedar claro: prohibicion o excepcion, vinculados cubiertos, interes social, aprobacion, informacion, garantias reciprocas, abstenciones y reporte.',
        'precedente' => 'Las garantias a vinculados son zona critica de conflicto de interes; deben tratarse como excepcionales y documentarse con soporte independiente.',
    ],
    'DEC-089' => [
        'decision' => 'Definir que mayoria se requiere para modificar sustancialmente el negocio principal, objeto, mercado, riesgo o modelo operativo.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 5, 17, 24, 30 y 31; Codigo de Comercio art. 420 y estatutos sobre reformas y direccion general; Ley 222/1995 art. 23 por diligencia y deber de informacion.',
        'ejemplo' => 'Cambiar el negocio principal, entrar a un sector regulado o abandonar una linea estrategica requerira mayoria calificada, estudio de impacto y periodo de informacion previo.',
        'claro' => 'Debe quedar claro: que se entiende por cambio sustancial, informacion previa, mayoria, organo, derechos de minorias, plan de transicion y control de riesgos.',
        'precedente' => 'Cambiar la actividad puede transformar el perfil de riesgo y el pacto familiar de continuidad; por eso requiere decision reforzada.',
    ],
    'DEC-090' => [
        'decision' => 'Definir mecanismo para resolver bloqueos, empates o vetos persistentes en decisiones estrategicas.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 40 sobre resolucion de conflictos societarios, art. 43 sobre abuso de voto y art. 24 sobre acuerdos. Ley 222/1995 arts. 19 a 21 para documentar acuerdos. Codigo General del Proceso y estatutos/acuerdos cuando se pacte arbitraje, amigable composicion o mediacion.',
        'ejemplo' => 'Si hay empate, se activara negociacion interna, luego mediacion con asesor externo y, si persiste, mecanismo de compra/venta, arbitraje o decision del organo competente segun materia.',
        'claro' => 'Debe quedar claro: plazo de bloqueo, etapas, mediador, perito, mecanismo final, continuidad operativa y decisiones que no pueden quedar suspendidas.',
        'precedente' => 'El bloqueo no resuelto destruye valor; el protocolo debe prever salida antes de que el conflicto llegue a litigio.',
    ],
];
$cat09LegalReferences = [
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 17, 24, 30 y 31 - Organizacion, acuerdos y reformas',
        'texto' => 'Permiten estructura flexible, acuerdos de accionistas, reglas de voto y reformas o transformaciones societarias cuando aplique.',
        'uso' => 'Base para mayorias, asuntos reservados, fundadores, veto, inversiones, nuevas sociedades y cambios de negocio.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 40, 42 y 43 - Conflictos, fraude y abuso del derecho',
        'texto' => 'Regulan solucion de conflictos societarios, actos defraudatorios y abuso del derecho de voto de mayoria, minoria o paridad.',
        'uso' => 'Base para bloqueos, empates, vetos, abuso y decisiones que perjudiquen a la sociedad o accionistas.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 19, 20 y 21 - Formalizacion de decisiones',
        'texto' => 'Regulan reuniones no presenciales, decisiones por comunicacion escrita y actas.',
        'uso' => 'Base documental para registrar aprobaciones, vetos, autorizaciones y excepciones.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 22, 23 y 24 - Administradores y conflictos',
        'texto' => 'Define administradores, deberes de buena fe, lealtad, diligencia, abstencion en conflictos y responsabilidad.',
        'uso' => 'Aplica a endeudamiento, inversiones, garantias a vinculados, activos esenciales y cambios de negocio.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 420, 434, 438 y 443',
        'texto' => 'Regulan funciones del maximo organo, atribuciones de junta y rendicion de cuentas del gerente cuando aplique.',
        'uso' => 'Ayuda a separar decisiones de accionistas, junta y administracion.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Art. 1602 - Fuerza obligatoria del contrato',
        'texto' => 'El contrato validamente celebrado obliga a quienes lo suscriben en los terminos de ley.',
        'uso' => 'Base general para acuerdos familiares o de accionistas sobre asuntos reservados.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 5 a 24 y 31 a 32',
        'texto' => 'Recomienda reglas de administracion, control, revelacion, comites, roles familiares y protocolo de familia.',
        'uso' => 'Marco consultivo para matriz de autorizaciones, asuntos reservados y separacion familia-empresa.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Supersociedades',
        'articulo' => 'Guia sobre gestion de conflictos de intereses',
        'texto' => 'Explica el alcance practico del art. 23 de la Ley 222 y la necesidad de revelar, abstenerse y obtener autorizacion en conflictos.',
        'uso' => 'Especialmente util para garantias a vinculados, inversiones con relacionados, vetos y decisiones con interes personal.',
        'url' => 'https://www.supersociedades.gov.co/documents/20122/1229078/GUIA-GESTION-CONFLICTO-INTERESES.pdf',
    ],
];
$cat10Academy = [
    'DEC-091' => [
        'decision' => 'Definir si ser familiar otorga o no derecho automatico a trabajar en la empresa familiar.',
        'fundamento' => 'Articulos guia: Constitucion art. 25 (trabajo en condiciones dignas y justas) y art. 53 (igualdad de oportunidades, estabilidad, remuneracion proporcional y primacia de la realidad). Guia Colombiana Medida 32 sobre separar roles de familiar, accionista, empleado y administrador. Codigo Sustantivo del Trabajo art. 23 sobre elementos del contrato laboral cuando aplique.',
        'ejemplo' => 'Ser familiar no dara derecho automatico a empleo, salario, cargo o contrato. Todo ingreso dependera de vacante real, perfil, proceso objetivo, contrato formal y evaluacion.',
        'claro' => 'Debe quedar claro: no hay empleo automatico, quien aprueba, requisitos, excepciones, diferencia entre accionista y empleado, y canal de postulacion.',
        'precedente' => 'La primacia de la realidad laboral implica que, si hay subordinacion, servicio y remuneracion, existe relacion laboral aunque se disfrace como ayuda familiar.',
    ],
    'DEC-092' => [
        'decision' => 'Definir que un familiar solo podra ingresar cuando exista una vacante real, presupuestada y previamente aprobada.',
        'fundamento' => 'Articulos guia: Constitucion art. 53 sobre igualdad de oportunidades y remuneracion proporcional. Codigo Sustantivo del Trabajo arts. 22 y 23 sobre contrato de trabajo y sus elementos. Ley 222/1995 art. 23 si administradores aprueban cargos deben actuar en interes de la sociedad.',
        'ejemplo' => 'Ningun cargo se creara solo para ubicar a un familiar; la vacante debe estar en organigrama, presupuesto y descripcion de cargo aprobada por gerencia o junta.',
        'claro' => 'Debe quedar claro: quien crea la vacante, presupuesto, perfil, jefe, remuneracion, aprobacion, soportes y prohibicion de cargos ficticios.',
        'precedente' => 'Crear cargos sin necesidad empresarial puede afectar interes social, clima laboral y equidad frente a trabajadores no familiares.',
    ],
    'DEC-093' => [
        'decision' => 'Definir formacion minima academica, tecnica o profesional para ingresar a la empresa segun nivel del cargo.',
        'fundamento' => 'Articulos guia: Constitucion art. 53 (capacitacion, adiestramiento e igualdad de oportunidades). Codigo Sustantivo del Trabajo art. 57 sobre obligaciones del empleador y art. 58 sobre obligaciones del trabajador. Guia Colombiana Medida 32.',
        'ejemplo' => 'Para cargos operativos se exigira formacion tecnica o experiencia equivalente; para cargos directivos, titulo profesional o trayectoria demostrable y formacion en gobierno corporativo.',
        'claro' => 'Debe quedar claro: nivel de estudio, equivalencias, certificaciones, excepciones, induccion obligatoria y plan de cierre de brechas.',
        'precedente' => 'La regla evita que el apellido sustituya la idoneidad y protege tanto a la empresa como al familiar que ingresa.',
    ],
    'DEC-094' => [
        'decision' => 'Definir si se exigira experiencia externa antes de vincular familiares, especialmente para cargos profesionales, directivos o de confianza.',
        'fundamento' => 'Articulos guia: Constitucion art. 53 sobre igualdad y capacitacion. Ley 222/1995 arts. 22, 23 y 24 si el familiar ejercera administracion. Guia Colombiana Medida 32 sobre requisitos, roles y limites de familiares.',
        'ejemplo' => 'Para cargos de direccion, el familiar debera acreditar minimo dos anos de experiencia externa o experiencia interna equivalente evaluada por un tercero independiente.',
        'claro' => 'Debe quedar claro: anos exigidos, tipo de experiencia, cargos exceptuados, quien valida, equivalencias y efecto de no cumplir.',
        'precedente' => 'La experiencia externa ayuda a profesionalizar criterio, disminuir dependencia familiar y aportar practicas de mercado.',
    ],
    'DEC-095' => [
        'decision' => 'Definir si familiares participaran en procesos de seleccion objetivos y comparables frente a candidatos no familiares.',
        'fundamento' => 'Articulos guia: Constitucion art. 13 (igualdad) y art. 53 (igualdad de oportunidades). Codigo Sustantivo del Trabajo arts. 22, 23, 57 y 58. Ley 222/1995 art. 23 si administradores seleccionan deben evitar conflictos y actuar en interes social.',
        'ejemplo' => 'Todo familiar participara en proceso con hoja de vida, entrevista, pruebas, verificacion de referencias y evaluacion por recursos humanos o asesor externo.',
        'claro' => 'Debe quedar claro: etapas, evaluadores, criterios, documentacion, manejo de conflicto, comunicacion de resultados y archivo del proceso.',
        'precedente' => 'La seleccion objetiva protege a la empresa de favoritismo y al familiar de cuestionamientos internos.',
    ],
    'DEC-096' => [
        'decision' => 'Definir si familiares tendran el mismo periodo de prueba, induccion y reglas contractuales que los demas trabajadores.',
        'fundamento' => 'Articulos guia: Codigo Sustantivo del Trabajo arts. 76, 77 y 78 sobre periodo de prueba, estipulacion y duracion. Constitucion art. 53 sobre igualdad y estabilidad. Codigo Sustantivo del Trabajo art. 23 sobre relacion laboral.',
        'ejemplo' => 'Los familiares tendran contrato escrito, periodo de prueba pactado por escrito, induccion, metas iniciales y evaluacion igual que cualquier trabajador.',
        'claro' => 'Debe quedar claro: duracion, forma escrita, evaluador, metas, consecuencias, induccion y prohibicion de trato informal por parentesco.',
        'precedente' => 'El periodo de prueba debe pactarse formalmente; la informalidad aumenta riesgo laboral y conflicto familiar.',
    ],
    'DEC-097' => [
        'decision' => 'Definir reglas de dependencia jerarquica para evitar que un familiar dependa directamente de padres, pareja, hermanos u otros parientes cercanos.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre conflictos de interes, lealtad y trato equitativo. Guia Colombiana Medida 32 sobre roles y limites. Constitucion art. 53 sobre igualdad de oportunidades y condiciones dignas.',
        'ejemplo' => 'Un familiar no podra reportar directamente a padre, madre, pareja, hermano o hijo, salvo autorizacion temporal con evaluador alterno y controles de conflicto.',
        'claro' => 'Debe quedar claro: parentescos restringidos, excepciones, jefe alterno, evaluador, conflicto de interes, quejas y controles.',
        'precedente' => 'La dependencia directa entre familiares dificulta evaluacion objetiva, disciplina, remuneracion y manejo de conflictos.',
    ],
    'DEC-098' => [
        'decision' => 'Definir quien evaluara el desempeno de familiares empleados y con que metodologia.',
        'fundamento' => 'Articulos guia: Constitucion art. 53 sobre remuneracion proporcional, capacitacion e igualdad. Codigo Sustantivo del Trabajo arts. 57 y 58 sobre obligaciones de empleador y trabajador. Ley 222/1995 art. 23 si hay administradores familiares.',
        'ejemplo' => 'El desempeno se evaluara anualmente por jefe no familiar o comite de talento, con indicadores, metas, competencias, retroalimentacion y plan de mejora.',
        'claro' => 'Debe quedar claro: evaluador, frecuencia, indicadores, consecuencias, plan de mejora, apelacion y archivo de evaluaciones.',
        'precedente' => 'La evaluacion documentada evita que la permanencia dependa de afecto familiar o de presiones de propiedad.',
    ],
    'DEC-099' => [
        'decision' => 'Definir criterios de ascenso, promocion, aumento salarial y acceso a cargos de direccion para familiares.',
        'fundamento' => 'Articulos guia: Constitucion art. 53 sobre igualdad, remuneracion proporcional y capacitacion. Ley 222/1995 arts. 22, 23 y 24 si la promocion lleva a administracion. Guia Colombiana Medida 32.',
        'ejemplo' => 'Los ascensos de familiares exigiran desempeno sobresaliente, vacante, competencias demostradas, evaluacion comparativa y aprobacion de organo definido.',
        'claro' => 'Debe quedar claro: criterios, evaluadores, mercado salarial, cargos criticos, conflicto de interes, plan de carrera y documentacion.',
        'precedente' => 'La promocion por parentesco deteriora autoridad y clima; la promocion por merito fortalece legitimidad familiar.',
    ],
    'DEC-100' => [
        'decision' => 'Definir cuando y bajo que procedimiento podra retirarse, no renovarse o despedirse a un familiar trabajador.',
        'fundamento' => 'Articulos guia: Codigo Sustantivo del Trabajo art. 62 sobre terminacion con justa causa; art. 64 sobre terminacion sin justa causa e indemnizacion cuando aplique; arts. 57, 58 y 60 sobre obligaciones y prohibiciones. Constitucion art. 53 sobre estabilidad, debido proceso y favorabilidad laboral.',
        'ejemplo' => 'El retiro de un familiar seguira el mismo procedimiento laboral de cualquier trabajador: hechos documentados, descargos cuando aplique, decision escrita, liquidacion y manejo familiar separado.',
        'claro' => 'Debe quedar claro: causales, debido proceso, autoridad que decide, manejo de conflicto, liquidacion, confidencialidad y continuidad como accionista o familiar.',
        'precedente' => 'El parentesco no elimina derechos laborales ni obliga a conservar indefinidamente a quien no cumple; el procedimiento debe ser formal y respetuoso.',
    ],
];
$cat10LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Arts. 13, 25 y 53 - Igualdad y trabajo',
        'texto' => 'Art. 13 protege igualdad; art. 25 reconoce el trabajo en condiciones dignas y justas; art. 53 incluye igualdad de oportunidades, estabilidad, remuneracion proporcional, capacitacion y primacia de la realidad.',
        'uso' => 'Base transversal para empleo familiar sin privilegios automaticos y con trato objetivo.',
    ],
    [
        'norma' => 'Codigo Sustantivo del Trabajo',
        'articulo' => 'Arts. 22 y 23 - Contrato de trabajo y elementos',
        'texto' => 'Regulan contrato laboral y sus elementos: prestacion personal, subordinacion y remuneracion.',
        'uso' => 'Base para diferenciar colaboracion familiar informal de verdadera relacion laboral.',
    ],
    [
        'norma' => 'Codigo Sustantivo del Trabajo',
        'articulo' => 'Arts. 57, 58 y 60 - Obligaciones y prohibiciones',
        'texto' => 'Regulan obligaciones del empleador, obligaciones del trabajador y prohibiciones del trabajador.',
        'uso' => 'Aplica a induccion, desempeno, disciplina, cumplimiento y reglas internas.',
    ],
    [
        'norma' => 'Codigo Sustantivo del Trabajo',
        'articulo' => 'Arts. 76, 77 y 78 - Periodo de prueba',
        'texto' => 'Definen periodo de prueba, forma de estipularlo y duracion maxima aplicable.',
        'uso' => 'Base directa de DEC-096 para familiares vinculados laboralmente.',
    ],
    [
        'norma' => 'Codigo Sustantivo del Trabajo',
        'articulo' => 'Arts. 62 y 64 - Terminacion del contrato',
        'texto' => 'Regulan terminacion con justa causa y terminacion sin justa causa con indemnizacion cuando aplique.',
        'uso' => 'Base de DEC-100 sobre retiro, despido o no continuidad de familiares empleados.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 22, 23 y 24 - Administradores y conflictos',
        'texto' => 'Define administradores, deberes de lealtad, buena fe, diligencia, trato equitativo, conflictos de interes y responsabilidad.',
        'uso' => 'Aplica si el familiar empleado ejerce administracion o si administradores aprueban empleo, ascenso o remuneracion de familiares.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 17 y 27 - Organizacion y responsabilidad',
        'texto' => 'Permite organizar internamente la S.A.S. y remite a responsabilidad de administradores.',
        'uso' => 'Sirve para formalizar politicas internas y rutas de aprobacion en empresas S.A.S.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medida 32 - Roles y limites de familiares',
        'texto' => 'Recomienda definir funciones, deberes, responsabilidades y limites de familiares como socios, accionistas, empleados o administradores.',
        'uso' => 'Marco consultivo principal para politica de empleo familiar y desarrollo profesional.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Ministerio de Justicia / doctrina laboral',
        'articulo' => 'Periodo de prueba y despido con justa causa',
        'texto' => 'LegalApp orienta sobre periodo de prueba y tramite de despido con justa causa, remitiendo al Codigo Sustantivo del Trabajo.',
        'uso' => 'Soporte pedagogico para explicar que el trato al familiar empleado debe ser formal, documentado y laboralmente correcto.',
    ],
];
$cat11Academy = [
    'DEC-101' => [
        'decision' => 'Definir si familiares empleados recibiran remuneracion de mercado segun cargo, responsabilidad, experiencia y desempeno.',
        'fundamento' => 'Articulos guia: Constitucion art. 53 sobre remuneracion minima vital y movil, proporcional a cantidad y calidad de trabajo, igualdad y primacia de la realidad. Codigo Sustantivo del Trabajo arts. 127 y 128 sobre salario y pagos no salariales. Ley 222/1995 art. 23 si administradores aprueban remuneraciones familiares deben actuar en interes social.',
        'ejemplo' => 'Los familiares empleados recibiran salario de mercado, definido por banda salarial, cargo, responsabilidades y evaluacion, sin sobrepagos por parentesco ni subpagos por confianza familiar.',
        'claro' => 'Debe quedar claro: banda salarial, fuente de mercado, aprobador, periodicidad de revision, beneficios incluidos y manejo de conflictos.',
        'precedente' => 'La remuneracion de mercado protege a la empresa, al familiar y a los demas trabajadores frente a favoritismo o informalidad.',
    ],
    'DEC-102' => [
        'decision' => 'Separar salario por trabajo, honorarios por organos, dividendos por propiedad y ayudas familiares extraordinarias.',
        'fundamento' => 'Articulos guia: Codigo Sustantivo del Trabajo arts. 127 y 128; Codigo de Comercio arts. 155 y 451 sobre distribucion de utilidades/dividendos cuando aplique; Ley 222/1995 arts. 23, 45, 46 y 47 sobre deberes, rendicion de cuentas y estados financieros.',
        'ejemplo' => 'El salario remunera trabajo; el dividendo remunera propiedad accionaria segun utilidades aprobadas; los honorarios remuneran asistencia a organos; las ayudas familiares no sustituyen prestaciones ni dividendos.',
        'claro' => 'Debe quedar claro: concepto, causa, aprobador, soporte contable, tratamiento tributario/laboral, beneficiarios y prohibicion de mezclar cuentas.',
        'precedente' => 'Mezclar salario y dividendo distorsiona estados financieros, puede crear riesgos laborales y genera reclamos entre accionistas que no trabajan.',
    ],
    'DEC-103' => [
        'decision' => 'Definir como se fijaran honorarios de familiares e independientes que integren juntas, comites o consejos.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 22, 23 y 24 si miembros de junta son administradores. Ley 1258/2008 arts. 17, 25 y 27 sobre junta y administradores en S.A.S. Codigo de Comercio arts. 434 y 438 cuando aplique. Guia Colombiana Medidas 8 a 12.',
        'ejemplo' => 'Los honorarios de junta o comites se fijaran por sesion o periodo, segun responsabilidad, mercado, preparacion y asistencia, con aprobacion del organo competente.',
        'claro' => 'Debe quedar claro: monto, periodicidad, asistencia minima, gastos reembolsables, impuestos, independencia, aprobacion y evaluacion.',
        'precedente' => 'El honorario debe responder a responsabilidad real; no debe usarse como dividendo encubierto ni beneficio familiar.',
    ],
    'DEC-104' => [
        'decision' => 'Definir si se permiten vehiculos, vivienda, viajes, seguros, tarjetas u otros beneficios especiales para familiares.',
        'fundamento' => 'Articulos guia: Codigo Sustantivo del Trabajo arts. 127 y 128 para determinar si ciertos beneficios constituyen salario o no. Estatuto Tributario arts. 107 y 107-1 sobre expensas necesarias y limitaciones de deducciones. Ley 222/1995 art. 23 sobre interes social y conflictos.',
        'ejemplo' => 'Los beneficios solo se permitiran si estan asociados al cargo, aprobados por politica, soportados documentalmente y con tratamiento laboral, contable y tributario definido.',
        'claro' => 'Debe quedar claro: beneficios permitidos, beneficiarios, limites, uso personal, soporte, aprobador, tratamiento salarial/tributario y reporte.',
        'precedente' => 'Beneficios sin politica se vuelven gasto personal, salario encubierto o privilegio familiar no justificado.',
    ],
    'DEC-105' => [
        'decision' => 'Definir que gastos personales o familiares estan prohibidos en cuentas, tarjetas, cajas o recursos de la empresa.',
        'fundamento' => 'Articulos guia: Estatuto Tributario art. 107 sobre causalidad, necesidad y proporcionalidad de expensas; Ley 222/1995 arts. 23 y 24 sobre deberes y responsabilidad de administradores; Codigo de Comercio art. 19 sobre deberes de comerciantes y contabilidad cuando aplique.',
        'ejemplo' => 'Quedan prohibidos gastos personales de mercado, vacaciones, colegios, eventos privados, remodelaciones familiares, consumo domestico o deudas personales cargadas a la empresa.',
        'claro' => 'Debe quedar claro: gastos prohibidos, excepciones autorizadas, reembolso, sanciones, conciliacion mensual y responsable de control.',
        'precedente' => 'Pagar gastos familiares con recursos sociales puede afectar deducibilidad, transparencia contable, responsabilidad de administradores y confianza entre socios.',
    ],
    'DEC-106' => [
        'decision' => 'Definir si la empresa puede prestar dinero a familiares, accionistas, administradores o vinculados.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 numeral 7 sobre conflictos de interes y autorizacion expresa; art. 24 responsabilidad. Ley 1258/2008 art. 43 sobre abuso del derecho. Estatuto Tributario arts. 35 y 260-1 y ss. cuando existan intereses presuntivos o vinculados economicos, segun analisis tributario.',
        'ejemplo' => 'La empresa no otorgara prestamos personales a familiares, salvo politica aprobada, interes social justificado, contrato escrito, tasa de mercado, garantias y abstencion de conflictuados.',
        'claro' => 'Debe quedar claro: si se prohibe o permite, monto maximo, tasa, plazo, garantias, aprobador, conflicto de interes, mora y reporte.',
        'precedente' => 'Los prestamos a familiares son operaciones con alto riesgo de conflicto y de extraccion irregular de recursos si no tienen contrato y condiciones de mercado.',
    ],
    'DEC-107' => [
        'decision' => 'Definir tasa, plazo, garantias, limites, mora y procedimiento de cobro de creditos a familiares si se permiten.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1602 sobre fuerza obligatoria de contratos validos; Codigo de Comercio reglas generales de obligaciones mercantiles cuando aplique. Ley 222/1995 art. 23 sobre conflictos e interes social. Estatuto Tributario art. 35 sobre componente inflacionario/intereses presuntivos cuando aplique.',
        'ejemplo' => 'Todo prestamo autorizado tendra contrato, tasa no inferior a referencia definida, plazo maximo, garantia suficiente, descuento autorizado o plan de pagos, y reporte trimestral.',
        'claro' => 'Debe quedar claro: tasa, plazo, garantia, desembolso, mora, cobro, refinanciacion, prohibicion de condonacion informal y archivo documental.',
        'precedente' => 'Sin condiciones de credito, el prestamo familiar termina convertido en anticipo, dividendo, gasto o conflicto.',
    ],
    'DEC-108' => [
        'decision' => 'Definir si la empresa puede respaldar obligaciones financieras personales de familiares o empresas relacionadas.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 numeral 7 sobre conflictos de interes; art. 24 responsabilidad. Guia de Supersociedades sobre gestion de conflictos de intereses. Ley 1258/2008 art. 43 sobre abuso del derecho. Codigo de Comercio reglas de garantias y organos competentes segun estatutos.',
        'ejemplo' => 'La empresa no garantizara deudas personales de familiares. Cualquier garantia a relacionado requerira interes social demostrado, aprobacion expresa del organo competente y condiciones de mercado.',
        'claro' => 'Debe quedar claro: prohibicion, excepciones, beneficiarios, interes social, contragarantias, limite, autorizacion, abstenciones y reporte.',
        'precedente' => 'Respaldar deudas personales con patrimonio social puede trasladar riesgos privados a todos los accionistas y acreedores.',
    ],
    'DEC-109' => [
        'decision' => 'Definir si existira fondo para emergencias, salud, educacion o ayudas extraordinarias de la familia, y si sera familiar o empresarial.',
        'fundamento' => 'Articulos guia: Constitucion art. 42 sobre proteccion familiar. Ley 222/1995 art. 23 si se usan recursos sociales, estos deben responder al interes de la sociedad. Estatuto Tributario arts. 107 y 107-1 para evaluar deducibilidad/limitaciones si el gasto se carga a la empresa.',
        'ejemplo' => 'El fondo de ayudas sera financiado por aportes familiares o dividendos ya distribuidos; no por caja de la empresa, salvo programas laborales generales aprobados y soportados.',
        'claro' => 'Debe quedar claro: fuente de recursos, beneficiarios, eventos cubiertos, monto, aprobador, confidencialidad, soporte y rendicion de cuentas.',
        'precedente' => 'Un fondo familiar puede ser sano si no confunde caja de empresa con caja de familia.',
    ],
    'DEC-110' => [
        'decision' => 'Definir como se aprueban contratos, compras, ventas, servicios, arrendamientos o negocios entre la empresa y familiares o sus empresas.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 numeral 7 exige abstencion y autorizacion expresa en conflictos de interes; art. 24 responsabilidad. Decreto 1074 de 2015 y Decreto 46 de 2024 reglamentan aspectos de conflictos de interes de administradores. Ley 1258/2008 art. 43 abuso de derecho. Guia Supersociedades de conflictos de intereses.',
        'ejemplo' => 'Toda operacion con familiar o vinculado requerira declaracion de conflicto, cotizaciones comparables, condiciones de mercado, aprobacion del organo competente y abstencion del interesado.',
        'claro' => 'Debe quedar claro: quien es vinculado, umbrales, documentos, precios de mercado, abstenciones, aprobador, seguimiento y publicacion interna.',
        'precedente' => 'Las operaciones con vinculados no son prohibidas por si mismas; el riesgo esta en ocultarlas, aprobarlas sin independencia o pactarlas fuera de mercado.',
    ],
];
$cat11LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Arts. 42 y 53 - Familia y reglas laborales',
        'texto' => 'Art. 42 protege la familia; art. 53 contiene principios laborales como igualdad, remuneracion proporcional, estabilidad y primacia de la realidad.',
        'uso' => 'Base para ayudas familiares y remuneracion de familiares empleados sin confundir familia y empresa.',
    ],
    [
        'norma' => 'Codigo Sustantivo del Trabajo',
        'articulo' => 'Arts. 127 y 128 - Salario y pagos no salariales',
        'texto' => 'Distinguen pagos que constituyen salario y pagos que no lo constituyen, segun naturaleza y pacto.',
        'uso' => 'Aplica a salarios, beneficios, vehiculos, vivienda, viajes y pagos a familiares empleados.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 155, 420, 434, 438 y 451',
        'texto' => 'Regulan distribucion de utilidades, funciones de organos, junta directiva y reparto de dividendos cuando aplique.',
        'uso' => 'Base para separar salario, honorario y dividendo; tambien para aprobaciones de organos.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 22, 23, 24, 45, 46 y 47',
        'texto' => 'Regulan administradores, deberes, conflictos, responsabilidad, rendicion de cuentas, estados financieros e informe de gestion.',
        'uso' => 'Aplica a remuneraciones, beneficios, prestamos, garantias y operaciones con vinculados.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 17, 24, 27 y 43',
        'texto' => 'Permiten organizar reglas internas, pactar acuerdos de accionistas, aplicar responsabilidad de administradores y controlar abuso de derecho.',
        'uso' => 'Sirve para formalizar politica de beneficios, prestamos, garantias y vinculados en S.A.S.',
    ],
    [
        'norma' => 'Estatuto Tributario',
        'articulo' => 'Arts. 35, 107, 107-1 y 260-1 y ss.',
        'texto' => 'Incluyen reglas sobre intereses presuntivos, expensas necesarias, limitaciones de deducciones y operaciones con vinculados economicos/precios de transferencia cuando aplique.',
        'uso' => 'Aplica a gastos personales, beneficios, prestamos, ayudas y operaciones con vinculados.',
    ],
    [
        'norma' => 'Decreto 1074 de 2015 / Decreto 46 de 2024',
        'articulo' => 'Conflictos de interes de administradores',
        'texto' => 'Reglamentan aspectos del deber de administradores frente a conflictos de interes y actos de competencia.',
        'uso' => 'Base practica para abstenciones, autorizacion expresa, informacion suficiente y aprobacion de operaciones con vinculados.',
    ],
    [
        'norma' => 'Guia Supersociedades',
        'articulo' => 'Gestion de conflictos de intereses',
        'texto' => 'Explica deberes de buena fe, lealtad, diligencia, revelacion, abstencion y autorizacion en conflictos.',
        'uso' => 'Documento consultivo clave para operaciones con familiares, prestamos, garantias y contratos relacionados.',
        'url' => 'https://www.supersociedades.gov.co/documents/20122/1229078/GUIA-GESTION-CONFLICTO-INTERESES.pdf',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 31, 32, 33 y 34',
        'texto' => 'Recomienda protocolo, separacion de roles y reglas sobre operaciones entre familiares y sociedad.',
        'uso' => 'Marco consultivo para ordenar relaciones economicas familia-empresa.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$cat12Academy = [
    'DEC-111' => [
        'decision' => 'Definir una politica ordinaria de dividendos: porcentaje, condiciones previas y excepciones.',
        'fundamento' => 'Articulos guia: Codigo de Comercio arts. 155 y 451 sobre distribucion de utilidades y mayorias cuando aplique; Ley 222/1995 arts. 45, 46 y 47 sobre rendicion de cuentas, estados financieros e informe de gestion; Ley 1258/2008 arts. 17 y 24 para reglas internas y acuerdos de accionistas.',
        'ejemplo' => 'La familia procurara distribuir hasta el 40% de utilidades netas disponibles, siempre que existan estados financieros aprobados, flujo de caja suficiente y cumplimiento de reservas.',
        'claro' => 'Debe quedar claro: porcentaje objetivo, base contable, condiciones, organo competente, fechas, excepciones y comunicacion a accionistas.',
        'precedente' => 'La politica de dividendos reduce expectativas irreales y evita que cada cierre anual se convierta en conflicto.',
    ],
    'DEC-112' => [
        'decision' => 'Definir porcentaje minimo o criterios de reinversion para crecimiento, mantenimiento, tecnologia, capital de trabajo o reduccion de deuda.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre diligencia e interes social de administradores; arts. 46 y 47 sobre informacion financiera e informe de gestion. Ley 1258/2008 arts. 17 y 24. Guia Colombiana Medidas 18 a 24 sobre informacion y control.',
        'ejemplo' => 'Antes de repartir dividendos, la empresa priorizara reinversion en mantenimiento, capital de trabajo, tecnologia, reposicion de activos y proyectos aprobados por junta.',
        'claro' => 'Debe quedar claro: porcentaje o formula, destinos permitidos, aprobador, indicadores, seguimiento y relacion con presupuesto anual.',
        'precedente' => 'Reinvertir no es negar dividendos; es proteger capacidad futura de generarlos.',
    ],
    'DEC-113' => [
        'decision' => 'Definir reservas legales, estatutarias, ocasionales o voluntarias que deben constituirse antes de repartir dividendos.',
        'fundamento' => 'Articulos guia: Codigo de Comercio arts. 452 y 453 sobre reserva legal y reservas ocasionales cuando aplique; Codigo de Comercio arts. 155 y 451 sobre utilidades. Ley 222/1995 arts. 46 y 47.',
        'ejemplo' => 'Antes de distribuir dividendos se constituira reserva legal cuando aplique, reserva de capital de trabajo, reserva de mantenimiento y reserva para contingencias aprobadas.',
        'claro' => 'Debe quedar claro: tipos de reserva, porcentaje, finalidad, duracion, liberacion, aprobacion y soporte financiero.',
        'precedente' => 'Las reservas bien explicadas evitan la lectura de que la administracion retiene utilidades sin razon.',
    ],
    'DEC-114' => [
        'decision' => 'Definir circunstancias en las que pueden suspenderse o reducirse dividendos aunque existan utilidades contables.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre deber de actuar en interes de la sociedad y art. 24 responsabilidad; Codigo de Comercio arts. 155, 451 y 456 sobre utilidades y pago cuando aplique; Ley 1258/2008 art. 24 para pactos de accionistas.',
        'ejemplo' => 'Los dividendos podran suspenderse por iliquidez, incumplimiento de covenants, perdidas acumuladas, obligaciones tributarias, litigios relevantes, expansion aprobada o riesgo de continuidad.',
        'claro' => 'Debe quedar claro: causales, indicador financiero, plazo, informacion a accionistas, revision y regla de recuperacion futura.',
        'precedente' => 'Utilidad contable no siempre significa caja disponible; repartir sin liquidez puede poner en riesgo la empresa.',
    ],
    'DEC-115' => [
        'decision' => 'Definir como equilibrar necesidades economicas familiares con necesidades de reinversion, liquidez y solvencia empresarial.',
        'fundamento' => 'Articulos guia: Constitucion art. 58 sobre propiedad y derechos adquiridos; Ley 222/1995 art. 23 sobre interes social; Guia Colombiana Medidas 31 y 32 sobre separar familia, propiedad y empresa.',
        'ejemplo' => 'La familia revisara anualmente necesidades de liquidez de accionistas, pero la decision de dividendos se subordinara a solvencia, presupuesto, obligaciones y plan estrategico.',
        'claro' => 'Debe quedar claro: informacion familiar considerada, limites, prioridad de empresa, alternativas de liquidez y responsable de explicar decisiones.',
        'precedente' => 'La empresa no debe convertirse en caja personal; tampoco puede ignorar que los propietarios tienen expectativas economicas legitimas.',
    ],
    'DEC-116' => [
        'decision' => 'Definir si los accionistas estaran obligados o no a aportar capital cuando la empresa lo requiera.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 5, 9, 17, 24 y 30 sobre capital, estatutos, reglas internas, acuerdos y reformas; Codigo de Comercio art. 384 sobre suscripcion de acciones cuando aplique; Codigo Civil art. 1602.',
        'ejemplo' => 'Los accionistas no tendran obligacion automatica de aportar capital, salvo compromiso escrito, acuerdo de accionistas o decision societaria validamente adoptada.',
        'claro' => 'Debe quedar claro: si hay obligacion, monto, plazo, forma, aprobacion, consecuencias y diferencia entre aporte, prestamo y capitalizacion.',
        'precedente' => 'La obligacion de aportar no debe presumirse por parentesco; debe nacer de estatutos, contrato o decision valida.',
    ],
    'DEC-117' => [
        'decision' => 'Definir que ocurre si algunos familiares no participan en una capitalizacion aprobada.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 9, 17, 24 y 30; Codigo de Comercio arts. 384 y 388 sobre suscripcion y derecho de preferencia cuando aplique; Codigo Civil art. 1602.',
        'ejemplo' => 'Quien no participe podra mantener su participacion si otros aportes se tratan como prestamo, o aceptar dilucion si se emiten acciones bajo reglas aprobadas y con derecho de preferencia respetado.',
        'claro' => 'Debe quedar claro: alternativas, plazo para aportar, preferencia, renuncia, dilucion, prestamo sustituto y registro documental.',
        'precedente' => 'La falta de aporte debe resolverse antes de necesitar el dinero; de lo contrario aparecen acusaciones de exclusion o privilegio.',
    ],
    'DEC-118' => [
        'decision' => 'Definir si se permitira la dilucion de accionistas que no aporten nuevos recursos y bajo que protecciones.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 9, 10, 11, 24, 30 y 43; Codigo de Comercio arts. 384 y 388 cuando aplique; Ley 222/1995 art. 23 por deber de trato equitativo e informacion suficiente.',
        'ejemplo' => 'La dilucion se permitira solo si hay emision formal, valoracion o precio justificado, derecho de preferencia, informacion previa y aprobacion del organo competente.',
        'claro' => 'Debe quedar claro: formula de emision, precio, preferencia, informacion, renuncia, mayorias, proteccion de minorias y registro.',
        'precedente' => 'La dilucion puede ser legitima para financiar crecimiento, pero abusiva si se usa para castigar o desplazar ramas familiares.',
    ],
    'DEC-119' => [
        'decision' => 'Definir circunstancias para admitir socios, fondos, inversionistas externos o aliados estrategicos.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 10, 11, 17, 24, 30 y 31 sobre clases de acciones, voto, acuerdos y reformas. Codigo de Comercio arts. 384, 388 y 420 cuando aplique. Ley 222/1995 art. 23 sobre diligencia e interes social.',
        'ejemplo' => 'Se podra admitir inversionista externo por expansion, tecnologia, deuda excesiva o relevo estrategico, con valoracion independiente, acuerdo de accionistas y proteccion del control familiar definida.',
        'claro' => 'Debe quedar claro: causales, tipo de inversionista, porcentaje, derechos, salida, informacion, veto, gobierno y impacto en control familiar.',
        'precedente' => 'El inversionista externo puede aportar valor, pero cambia gobierno, informacion y poder; debe definirse antes de negociar.',
    ],
    'DEC-120' => [
        'decision' => 'Definir si familiares pueden prestar recursos a la empresa y bajo que condiciones de mercado, prelacion y aprobacion.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre conflictos de interes e interes social; Codigo Civil art. 1602; Codigo de Comercio reglas de obligaciones mercantiles cuando aplique. Estatuto Tributario arts. 35 y 107 para revisar intereses y deducibilidad segun caso.',
        'ejemplo' => 'Los prestamos de familiares a la empresa requeriran contrato, tasa de mercado, plazo, garantias o subordinacion definida, aprobacion sin voto del interesado y reporte financiero.',
        'claro' => 'Debe quedar claro: prestamista, monto, tasa, plazo, garantias, prelacion, subordinacion, conversion a capital, mora y conflicto de interes.',
        'precedente' => 'El prestamo familiar puede salvar liquidez, pero sin reglas puede convertirse en presion de control o preferencia indebida.',
    ],
];
$cat12LegalReferences = [
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 155, 451, 452, 453 y 456 - Utilidades, dividendos y reservas',
        'texto' => 'Regulan distribucion de utilidades, pago de dividendos y constitucion de reservas legales u ocasionales cuando aplique.',
        'uso' => 'Base para politica de dividendos, reinversion, reservas y suspension de pagos.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 384, 388 y 420 - Suscripcion, preferencia y organo maximo',
        'texto' => 'Regulan suscripcion de acciones, derecho de preferencia cuando aplique y funciones del maximo organo social.',
        'uso' => 'Aplica a capitalizaciones, falta de aporte, dilucion e ingreso de inversionistas.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 5, 9, 10, 11, 17, 24, 30, 31 y 43',
        'texto' => 'Regulan constitucion, capital, clases de acciones, voto, organizacion, acuerdos, reformas, transformacion y abuso del derecho.',
        'uso' => 'Base para S.A.S., pactos de accionistas, dilucion, inversionistas externos y reglas de control.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 23, 24, 45, 46 y 47',
        'texto' => 'Regulan deberes y responsabilidad de administradores, rendicion de cuentas, estados financieros e informe de gestion.',
        'uso' => 'Aplica a decisiones financieras, informacion a accionistas y balance entre dividendos y sostenibilidad.',
    ],
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Art. 58 - Propiedad privada',
        'texto' => 'Protege propiedad privada y derechos adquiridos dentro de los limites legales.',
        'uso' => 'Sirve para explicar expectativas patrimoniales de accionistas y limites a diluciones o aportes no pactados.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Art. 1602 - Fuerza obligatoria del contrato',
        'texto' => 'Los contratos validamente celebrados obligan a quienes los suscriben.',
        'uso' => 'Base general para acuerdos de capitalizacion, prestamos familiares y compromisos de aporte.',
    ],
    [
        'norma' => 'Estatuto Tributario',
        'articulo' => 'Arts. 35 y 107',
        'texto' => 'Orientan revision de intereses presuntivos y deducibilidad de expensas necesarias cuando se pacten prestamos o costos financieros.',
        'uso' => 'Aplica a prestamos familiares a la empresa e intereses pactados.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 18 a 24, 31 y 32',
        'texto' => 'Recomienda informacion, control, transparencia, protocolo y separacion de roles familia-propiedad-empresa.',
        'uso' => 'Marco consultivo para politica financiera familiar y decisiones de capital.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$cat13Academy = [
    'DEC-121' => [
        'decision' => 'Definir con que frecuencia se valoraran empresas, acciones o participaciones familiares.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 34, 35, 36, 37 y 38 sobre estados financieros, certificacion y dictamen; arts. 45 a 47 sobre rendicion de cuentas e informe de gestion. Ley 1258/2008 arts. 17 y 24 para pactar reglas de valoracion en estatutos o acuerdos de accionistas.',
        'ejemplo' => 'Las sociedades estrategicas se valoraran cada dos anos y de forma extraordinaria ante venta, retiro, fallecimiento, ingreso de inversionista, litigio relevante o cambio material del negocio.',
        'claro' => 'Debe quedar claro: periodicidad, sociedades incluidas, eventos extraordinarios, responsable, presupuesto, documentos base y vigencia del valor.',
        'precedente' => 'Una valoracion periodica evita que el precio se improvise justo cuando ya existe tension por salida, sucesion o venta.',
    ],
    'DEC-122' => [
        'decision' => 'Definir metodos aceptados de valoracion: flujo de caja descontado, multiplos, valor patrimonial, avaluos o combinaciones.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 34 a 38 sobre confiabilidad de estados financieros. Decreto 2420 de 2015 sobre marcos tecnicos contables NIIF/Normas de Informacion Financiera, cuando aplique. Ley 1258/2008 art. 24 para pactar metodologia en acuerdos de accionistas.',
        'ejemplo' => 'Para empresas operativas se usara flujo de caja descontado y multiplos comparables; para sociedades patrimoniales se usara valor neto de activos ajustado por avaluos independientes.',
        'claro' => 'Debe quedar claro: metodo principal, metodo de contraste, supuestos, deuda, caja, contingencias, normalizaciones y tratamiento de activos no operativos.',
        'precedente' => 'No todos los negocios se valoran igual; una sociedad inmobiliaria y una empresa operativa requieren logicas distintas.',
    ],
    'DEC-123' => [
        'decision' => 'Definir quien seleccionara al valorador independiente y que requisitos de independencia, experiencia y confidencialidad debe cumplir.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre deber de administradores de actuar con lealtad, diligencia y sin conflicto de interes. Guia Colombiana de Gobierno Corporativo Medidas 8 a 10 y 18 a 24 sobre independencia, informacion y control.',
        'ejemplo' => 'El valorador sera escogido por la junta o comite designado a partir de tres propuestas, con declaracion de independencia, experiencia sectorial y acuerdo de confidencialidad.',
        'claro' => 'Debe quedar claro: organo que selecciona, lista corta, criterios, inhabilidades, honorarios, acceso a informacion y deber de reserva.',
        'precedente' => 'La independencia del valorador es tan importante como el metodo; si la familia no confia en quien valora, no confiara en el resultado.',
    ],
    'DEC-124' => [
        'decision' => 'Definir fecha de corte para ventas, retiros, fallecimientos, separaciones, capitalizaciones o ingreso de terceros.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 34 a 38 y 45 a 47 sobre estados financieros e informacion base. Ley 1258/2008 art. 24 sobre acuerdos de accionistas. Codigo Civil art. 1602 sobre fuerza obligatoria de acuerdos validos.',
        'ejemplo' => 'La fecha de corte sera el cierre mensual inmediatamente anterior al evento que activa la valoracion, salvo fallecimiento, donde se usara la fecha del deceso o el corte contable mas cercano.',
        'claro' => 'Debe quedar claro: evento activador, corte contable, ajustes posteriores, contingencias, moneda, auditoria y vigencia del informe.',
        'precedente' => 'La fecha de corte evita manipular el precio eligiendo un momento favorable a comprador o vendedor.',
    ],
    'DEC-125' => [
        'decision' => 'Definir como se valoraran marcas, software, bases de datos, conocimiento, reputacion, contratos, licencias y otros intangibles.',
        'fundamento' => 'Articulos guia: Decision Andina 486 de 2000 sobre propiedad industrial; Ley 23 de 1982 sobre derecho de autor; Decreto 2420 de 2015 sobre normas contables aplicables a intangibles cuando correspondan. Ley 222/1995 arts. 34 a 38 sobre estados financieros.',
        'ejemplo' => 'Los intangibles se valoraran si tienen titularidad, soporte, uso economico y capacidad de generar beneficios; se documentaran marcas, software, dominios, contratos y bases de datos relevantes.',
        'claro' => 'Debe quedar claro: titular, soporte legal, metodo, vida util, ingresos asociados, riesgos, registro, uso por vinculados y tratamiento contable.',
        'precedente' => 'Un intangible sin titularidad o soporte puede tener valor comercial discutible aunque sea importante para la familia.',
    ],
    'DEC-126' => [
        'decision' => 'Definir si se reconocera prima de control cuando la participacion vendida otorgue capacidad de decidir o bloquear asuntos relevantes.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 10, 11, 24 y 43 sobre clases de acciones, voto, acuerdos y abuso del derecho. Codigo de Comercio art. 379 sobre derechos de las acciones. Ley 222/1995 art. 23 sobre trato equitativo e interes social.',
        'ejemplo' => 'Se reconocera prima de control solo cuando el paquete vendido otorgue control efectivo, voto decisivo o facultad de bloqueo, y el valorador la justifique tecnicamente.',
        'claro' => 'Debe quedar claro: que es control, porcentaje, derechos politicos, metodo para prima, limites, quien paga y cuando no aplica.',
        'precedente' => 'No toda accion vale igual si unas permiten controlar y otras solo participar economicamente.',
    ],
    'DEC-127' => [
        'decision' => 'Definir si se aplicaran descuentos por minoria, falta de liquidez, restricciones de transferencia o ausencia de control.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 sobre restricciones de transferencia y acuerdos; art. 43 sobre abuso del derecho. Codigo de Comercio arts. 403 y 407 sobre negociabilidad y preferencia cuando aplique. Ley 222/1995 art. 23.',
        'ejemplo' => 'Los descuentos por minoria o falta de liquidez solo se aplicaran si estan previstos en la politica, son tecnicamente sustentados y no se usan para castigar a una rama o accionista saliente.',
        'claro' => 'Debe quedar claro: descuentos permitidos, rangos, justificacion, restricciones consideradas, excepciones y proteccion contra abuso.',
        'precedente' => 'Los descuentos pueden reflejar realidad de mercado, pero tambien pueden convertirse en mecanismo de presion si no se limitan.',
    ],
    'DEC-128' => [
        'decision' => 'Definir como se resolveran diferencias entre dos valoraciones o desacuerdos sobre supuestos, ajustes o precio.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 40 sobre resolucion de conflictos societarios; art. 24 sobre acuerdos de accionistas. Ley 1563 de 2012 sobre arbitraje nacional e internacional si se pacta. Ley 222/1995 arts. 19 a 21 para actas y decisiones.',
        'ejemplo' => 'Si dos valoraciones difieren mas del 10%, se nombrara tercer valorador; el precio sera el promedio de las dos mas cercanas o el resultado del tercero, segun regla pactada.',
        'claro' => 'Debe quedar claro: tolerancia de diferencia, tercer valorador, costo, plazo, informacion base, metodo final y obligacion de aceptar resultado.',
        'precedente' => 'La regla de desempate debe existir antes del desacuerdo; si se negocia despues, cada parte defendera su propio precio.',
    ],
    'DEC-129' => [
        'decision' => 'Definir si la compra de acciones se pagara de contado, por cuotas, con garantia, con retencion o con mecanismos mixtos.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1602 y reglas generales de obligaciones; Codigo de Comercio arts. 403 y 407 sobre negociacion de acciones cuando aplique; Ley 1258/2008 art. 24 para acuerdos de compra, venta y forma de pago.',
        'ejemplo' => 'La compra podra pagarse con cuota inicial del 30% y saldo en cuotas trimestrales, con garantia real o prendaria, intereses pactados y aceleracion por mora.',
        'claro' => 'Debe quedar claro: contado o cuotas, cuota inicial, plazo, garantia, mora, transferencia de acciones, derechos mientras se paga y consecuencias de incumplir.',
        'precedente' => 'El precio sin forma de pago no resuelve la salida; puede crear un nuevo conflicto por liquidez o incumplimiento.',
    ],
    'DEC-130' => [
        'decision' => 'Definir plazo, interes, garantia, indexacion y condiciones financieras en compras financiadas de acciones.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1602; Codigo de Comercio reglas de intereses mercantiles y obligaciones cuando aplique; Ley 1258/2008 art. 24. Estatuto Tributario art. 35 y normas fiscales aplicables a intereses entre vinculados, segun revision tributaria.',
        'ejemplo' => 'Las compras financiadas tendran plazo maximo de cinco anos, tasa equivalente a referencia bancaria o IPC mas margen, garantia sobre acciones y prohibicion de dividendos al comprador en mora.',
        'claro' => 'Debe quedar claro: plazo, tasa, indexacion, garantia, mora, prepago, dividendos, seguros, cesion y solucion de controversias.',
        'precedente' => 'La financiacion debe equilibrar salida justa para el vendedor y viabilidad de pago para comprador o familia.',
    ],
];
$cat13LegalReferences = [
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 34, 35, 36, 37 y 38 - Estados financieros',
        'texto' => 'Regulan preparacion, certificacion y dictamen de estados financieros como base de informacion financiera confiable.',
        'uso' => 'Base de valoraciones, fechas de corte, supuestos y soportes contables.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 23, 45, 46 y 47',
        'texto' => 'Regulan deberes de administradores, rendicion de cuentas, estados financieros e informe de gestion.',
        'uso' => 'Aplica a informacion usada por valoradores, independencia y trato equitativo.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 10, 11, 17, 24, 40 y 43',
        'texto' => 'Regulan clases de acciones, voto, organizacion, acuerdos de accionistas, conflictos y abuso de derecho.',
        'uso' => 'Base para prima de control, descuentos, restricciones, metodo pactado y solucion de diferencias.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 379, 403 y 407',
        'texto' => 'Regulan derechos de acciones, negociabilidad y derecho de preferencia cuando aplique.',
        'uso' => 'Aplica a venta, compra, restricciones, preferencia y valor de paquetes accionarios.',
    ],
    [
        'norma' => 'Decreto 2420 de 2015',
        'articulo' => 'Marcos tecnicos contables',
        'texto' => 'Compila normas de informacion financiera aplicables segun grupo contable.',
        'uso' => 'Base para estados financieros, intangibles, valor razonable y revelaciones cuando corresponda.',
    ],
    [
        'norma' => 'Decision Andina 486 de 2000 / Ley 23 de 1982',
        'articulo' => 'Propiedad industrial y derecho de autor',
        'texto' => 'Regulan derechos sobre marcas, signos distintivos, software, obras y otros intangibles protegibles.',
        'uso' => 'Aplica a valoracion de marcas, software, bases de datos, reputacion y conocimiento documentado.',
    ],
    [
        'norma' => 'Ley 1563 de 2012',
        'articulo' => 'Arbitraje',
        'texto' => 'Regula arbitraje nacional e internacional cuando las partes pactan someter controversias a tribunal arbitral.',
        'uso' => 'Base para resolver diferencias de valoracion si se pacta arbitraje o mecanismo equivalente.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Art. 1602 - Fuerza obligatoria del contrato',
        'texto' => 'Los contratos validamente celebrados obligan a quienes los suscriben.',
        'uso' => 'Base para forma de pago, financiacion, garantias y aceptacion de reglas de valoracion.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 18 a 24, 31 y 32',
        'texto' => 'Recomienda informacion, transparencia, control, protocolo y separacion de roles familia-propiedad-empresa.',
        'uso' => 'Marco consultivo para valorar con informacion confiable y reglas previas.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$cat14Academy = [
    'DEC-131' => [
        'decision' => 'Definir que activos conforman el patrimonio empresarial y familiar, con identificacion, titularidad, valor, uso, riesgos y soportes.',
        'fundamento' => 'Articulos guia: Constitucion art. 58 sobre propiedad privada. Ley 222/1995 arts. 34 a 38 y 45 a 47 sobre informacion financiera y rendicion de cuentas. Ley 1581/2012 arts. 4, 9 y 17 si el inventario contiene datos personales. Codigo de Comercio art. 19 sobre deber de llevar contabilidad cuando aplique.',
        'ejemplo' => 'La familia mantendra inventario anual de sociedades, inmuebles, vehiculos, marcas, inversiones, cuentas, deudas, seguros y garantias, diferenciando titularidad legal y uso familiar.',
        'claro' => 'Debe quedar claro: activo, titular, porcentaje, ubicacion, valor, soporte, uso, renta, gravamen, seguro, responsable y fecha de actualizacion.',
        'precedente' => 'Lo que no esta inventariado no se gobierna; sin mapa patrimonial la familia decide con memoria incompleta.',
    ],
    'DEC-132' => [
        'decision' => 'Definir como se separaran bienes personales, familiares, societarios y de terceros para evitar confusion patrimonial.',
        'fundamento' => 'Articulos guia: Constitucion art. 58; Codigo de Comercio arts. 98 y 110 sobre sociedad y escritura/estatutos cuando aplique; Ley 1258/2008 arts. 2 y 5 sobre personeria juridica y constitucion de S.A.S.; Ley 222/1995 art. 23 sobre deber de administradores de proteger interes social.',
        'ejemplo' => 'Los bienes de la empresa no se usaran como bienes personales; todo uso de activos sociales por familiares requerira contrato, autorizacion, canon o compensacion y soporte contable.',
        'claro' => 'Debe quedar claro: titularidad, uso permitido, contratos, pagos, autorizaciones, gastos, mantenimiento, impuestos y prohibiciones.',
        'precedente' => 'La confusion patrimonial debilita gobierno, contabilidad, impuestos, responsabilidad y confianza entre ramas.',
    ],
    'DEC-133' => [
        'decision' => 'Definir reglas para uso, arriendo, administracion, mantenimiento, valorizacion, venta o permuta de inmuebles familiares.',
        'fundamento' => 'Articulos guia: Codigo Civil arts. 669 y ss. sobre dominio; Ley 820/2003 para arrendamiento de vivienda urbana cuando aplique; Codigo de Comercio para arrendamientos comerciales segun contrato; Ley 222/1995 art. 23 si el inmueble pertenece a sociedad.',
        'ejemplo' => 'Todo inmueble familiar tendra ficha de titularidad, avaluo, canon de mercado, responsable de mantenimiento, regla de uso por familiares y autorizacion para venta o gravamen.',
        'claro' => 'Debe quedar claro: titular, ocupante, canon, contrato, gastos, impuestos, seguros, mejoras, venta, preferencia y solucion de conflictos.',
        'precedente' => 'El uso informal de inmuebles es una de las fuentes mas comunes de desigualdad percibida entre familiares.',
    ],
    'DEC-134' => [
        'decision' => 'Definir si familiares pueden usar vehiculos, oficinas, casas, equipos, tarjetas, personal u otros activos empresariales.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre interes social y conflictos de interes; Estatuto Tributario art. 107 sobre expensas necesarias; Codigo Sustantivo del Trabajo arts. 127 y 128 si el uso constituye beneficio laboral o salarial.',
        'ejemplo' => 'Los familiares no podran usar activos empresariales para fines personales salvo autorizacion, contrato, tarifa definida, seguro vigente y registro del uso.',
        'claro' => 'Debe quedar claro: activos incluidos, usos permitidos, autorizacion, tarifa, responsable, seguros, danos, gastos y sanciones.',
        'precedente' => 'El uso gratuito de activos sociales puede ser beneficio encubierto, gasto no deducible o conflicto con otros propietarios.',
    ],
    'DEC-135' => [
        'decision' => 'Definir si conviene concentrar activos en una sociedad patrimonial, holding, fiducia u otra estructura de administracion.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 5, 17, 24, 30 y 31 sobre S.A.S., organizacion, acuerdos y reformas. Codigo de Comercio sobre sociedades. Estatuto Organico del Sistema Financiero y normas fiduciarias si se usa fiducia. Constitucion art. 58.',
        'ejemplo' => 'La familia evaluara una holding patrimonial para administrar participaciones, inmuebles e inversiones, previo concepto legal, tributario, contable y sucesoral.',
        'claro' => 'Debe quedar claro: finalidad, activos incluidos, gobierno, beneficiarios, costos, impuestos, administracion, salida y riesgos.',
        'precedente' => 'Una estructura patrimonial puede ordenar, pero mal disenada puede aumentar costos, impuestos o conflictos de control.',
    ],
    'DEC-136' => [
        'decision' => 'Definir limites de concentracion por sector, activo, empresa, inmueble, pais, moneda o tipo de riesgo.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre diligencia de administradores. Guia Colombiana Medidas 18 a 24 sobre control, informacion, riesgos y transparencia. Ley 1258/2008 arts. 17 y 24 para pactar politicas de inversion.',
        'ejemplo' => 'Ningun activo o sector debera representar mas del porcentaje definido del patrimonio consolidado, salvo activos historicos aprobados y monitoreados con plan de diversificacion.',
        'claro' => 'Debe quedar claro: limites, medicion, excepciones, periodicidad, responsable, liquidez, riesgos y plan de reduccion.',
        'precedente' => 'La concentracion puede crear riqueza, pero tambien fragilidad; la politica evita depender de un solo activo o negocio.',
    ],
    'DEC-137' => [
        'decision' => 'Definir politica de inversion: riesgos aceptados, sectores prohibidos, instrumentos permitidos, liquidez, moneda y aprobaciones.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre diligencia e interes social. Ley 1258/2008 arts. 17 y 24. Guia Colombiana Medidas 18 a 24. Normas del mercado de valores si se invierte en instrumentos regulados mediante intermediarios vigilados.',
        'ejemplo' => 'La familia permitira inversiones en renta fija, fondos diversificados, inmuebles productivos y negocios aprobados; prohibira inversiones no entendidas, sin soporte o con conflicto no revelado.',
        'claro' => 'Debe quedar claro: perfil de riesgo, instrumentos, limites, liquidez, custodio, asesor, reportes, conflictos y activos prohibidos.',
        'precedente' => 'La politica de inversion reduce decisiones emocionales y protege patrimonio de ofertas oportunistas.',
    ],
    'DEC-138' => [
        'decision' => 'Definir que activos familiares podran garantizar obligaciones empresariales y bajo que condiciones.',
        'fundamento' => 'Articulos guia: Constitucion art. 58; Ley 222/1995 art. 23 sobre interes social y conflictos; Codigo Civil y Codigo de Comercio sobre garantias, hipoteca, prenda y obligaciones segun activo. Ley 1258/2008 art. 24 para pactos entre accionistas.',
        'ejemplo' => 'Los activos familiares solo garantizaran obligaciones empresariales estrategicas con autorizacion reforzada, aval de riesgo, contragarantia y limite de exposicion.',
        'claro' => 'Debe quedar claro: activos autorizados, deuda cubierta, monto, plazo, liberacion, contragarantia, aprobador y consecuencias de incumplimiento.',
        'precedente' => 'Garantizar deuda empresarial con patrimonio familiar puede proteger continuidad o poner en riesgo vivienda, liquidez y unidad familiar.',
    ],
    'DEC-139' => [
        'decision' => 'Definir bienes, personas y riesgos que deben mantenerse asegurados: vida, key person, inmuebles, responsabilidad civil, cumplimiento, cyber, transporte u otros.',
        'fundamento' => 'Articulos guia: Codigo de Comercio arts. 1036 y ss. sobre contrato de seguro. Ley 222/1995 art. 23 sobre administracion diligente de riesgos. Guia Colombiana Medidas 18 a 24 sobre control y gestion de riesgos.',
        'ejemplo' => 'Se mantendran seguros de inmuebles, responsabilidad civil, vehiculos, vida de personas clave, cumplimiento contractual y riesgos digitales, con revision anual de coberturas.',
        'claro' => 'Debe quedar claro: activo/persona asegurada, cobertura, valor, beneficiario, prima, renovacion, responsable, exclusiones y soportes.',
        'precedente' => 'El seguro no evita el riesgo, pero evita que un siniestro destruya liquidez o fuerce venta de activos.',
    ],
    'DEC-140' => [
        'decision' => 'Definir mayoria, procedimiento y requisitos para vender activos patrimoniales relevantes.',
        'fundamento' => 'Articulos guia: Constitucion art. 58; Codigo de Comercio art. 420 sobre funciones del maximo organo cuando aplique; Ley 1258/2008 arts. 17, 24 y 30; Ley 222/1995 arts. 19 a 21 para documentar decisiones y art. 23 sobre diligencia.',
        'ejemplo' => 'La venta de activos relevantes requerira avaluo independiente, informacion previa, concepto tributario, aprobacion por mayoria reforzada y definicion del destino de recursos.',
        'claro' => 'Debe quedar claro: que es activo relevante, umbral, avaluo, mayorias, preferencia familiar, destino del precio, impuestos y plazo.',
        'precedente' => 'La venta de activos familiares tiene carga economica y emocional; por eso debe tener regla previa y trazable.',
    ],
];
$cat14LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Art. 58 - Propiedad privada',
        'texto' => 'Protege la propiedad privada y derechos adquiridos dentro de los limites legales y la funcion social/ecologica.',
        'uso' => 'Base transversal para inventario, separacion patrimonial, garantias y venta de activos.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 2, 5, 17, 24, 30 y 31',
        'texto' => 'Regulan personeria juridica, constitucion, organizacion, acuerdos de accionistas, reformas y transformacion de S.A.S.',
        'uso' => 'Base para holding, separacion patrimonial, politicas de inversion y reglas de aprobacion.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 19 a 24 y 34 a 47',
        'texto' => 'Regulan actas, administradores, deberes, responsabilidad, estados financieros, rendicion de cuentas e informe de gestion.',
        'uso' => 'Aplica a inventario, uso de activos, garantias, seguros, venta y control patrimonial.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Arts. 669 y ss. - Dominio y bienes',
        'texto' => 'Regulan el derecho de dominio y reglas generales sobre bienes.',
        'uso' => 'Base para titularidad, uso, administracion y venta de activos familiares.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Arts. 19, 98, 110, 420 y 1036 y ss.',
        'texto' => 'Regulan deberes mercantiles, sociedades, escritura/estatutos, organos y contrato de seguro.',
        'uso' => 'Aplica a sociedades patrimoniales, decisiones sobre activos y seguros.',
    ],
    [
        'norma' => 'Ley 820 de 2003',
        'articulo' => 'Arrendamiento de vivienda urbana',
        'texto' => 'Regula aspectos de arrendamiento de vivienda urbana cuando el inmueble tenga ese destino.',
        'uso' => 'Aplica a inmuebles familiares usados o arrendados como vivienda.',
    ],
    [
        'norma' => 'Estatuto Tributario',
        'articulo' => 'Art. 107 y normas relacionadas',
        'texto' => 'Exige causalidad, necesidad y proporcionalidad para deducibilidad de expensas.',
        'uso' => 'Aplica al uso de activos empresariales por familiares y gastos asociados.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 18 a 24, 31 y 32',
        'texto' => 'Recomienda informacion, control, riesgos, protocolo y separacion de roles familia-propiedad-empresa.',
        'uso' => 'Marco consultivo para mapa patrimonial, inversion, riesgos y proteccion de activos.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$cat15Academy = [
    'DEC-141' => [
        'decision' => 'Definir inventario de aplicaciones, plataformas, marcas, dominios, bases de datos, metodologias, repositorios, licencias y activos tecnologicos de cada empresa.',
        'fundamento' => 'Articulos guia: Ley 23/1982 arts. 1 y 2 sobre proteccion de obras; Decision Andina 486/2000 sobre propiedad industrial; Ley 1581/2012 arts. 4, 9 y 17 sobre datos personales; Ley 222/1995 arts. 34 a 38 sobre informacion financiera y soportes.',
        'ejemplo' => 'Cada empresa tendra inventario semestral de software, dominios, marcas, repositorios, bases de datos, cuentas SaaS, APIs, licencias y responsables de acceso.',
        'claro' => 'Debe quedar claro: activo, titular, usuario, proveedor, acceso, vencimiento, costo, soporte contractual, datos tratados, riesgo y responsable.',
        'precedente' => 'Los activos tecnologicos invisibles suelen ser criticos; si no se inventarian, pueden perderse con la salida de un proveedor o empleado.',
    ],
    'DEC-142' => [
        'decision' => 'Definir a nombre de que sociedad o titular quedaran los derechos patrimoniales sobre cada desarrollo tecnologico o creativo.',
        'fundamento' => 'Articulos guia: Ley 23/1982 arts. 1, 2, 12 y 183 sobre derechos de autor y transferencia de derechos patrimoniales; Decision Andina 351/1993 sobre derecho de autor; Ley 1258/2008 arts. 5 y 17 si la titularidad se ordena por estructura societaria.',
        'ejemplo' => 'Todo desarrollo contratado para la operacion quedara a nombre de la sociedad que lo paga y explota, con contrato de cesion y autorizacion de uso suficiente.',
        'claro' => 'Debe quedar claro: titular, autor/desarrollador, contrato, alcance de cesion, territorio, tiempo, modalidades de explotacion, codigo fuente y mejoras.',
        'precedente' => 'Pagar un desarrollo no siempre equivale a ser titular pleno; la cesion debe quedar expresa y utilizable.',
    ],
    'DEC-143' => [
        'decision' => 'Definir si empleados, contratistas, agencias, freelancers o familiares desarrolladores deben firmar cesion de derechos patrimoniales y confidencialidad.',
        'fundamento' => 'Articulos guia: Ley 23/1982 art. 183 sobre transferencia de derechos patrimoniales; Codigo Civil art. 1602; Codigo Sustantivo del Trabajo si hay relacion laboral; Ley 222/1995 art. 23 sobre deber de proteger interes social.',
        'ejemplo' => 'Todo proveedor o empleado que cree software, disenos, contenidos, bases, manuales o metodologias firmara contrato con cesion de derechos, confidencialidad y entrega de fuentes.',
        'claro' => 'Debe quedar claro: obras cubiertas, entregables, repositorio, codigo, documentacion, licencias, garantia de originalidad, datos y uso posterior.',
        'precedente' => 'La falta de cesion puede impedir vender, licenciar, registrar o modificar un activo que la empresa considera suyo.',
    ],
    'DEC-144' => [
        'decision' => 'Definir donde se custodiara el codigo fuente y quienes tendran acceso autorizado, con reglas de seguridad, respaldo y continuidad.',
        'fundamento' => 'Articulos guia: Ley 1581/2012 arts. 4, 9 y 17 si el codigo o ambientes acceden a datos personales; Ley 23/1982 sobre software como obra protegida; Ley 1273/2009 sobre delitos informaticos y proteccion de informacion y datos.',
        'ejemplo' => 'El codigo fuente se custodiará en repositorio corporativo con control de accesos, doble factor, respaldos, ramas protegidas, bitacora y acceso minimo necesario.',
        'claro' => 'Debe quedar claro: repositorio, administrador, accesos, respaldos, claves, emergencia, auditoria, salida de usuarios y propietario del codigo.',
        'precedente' => 'Si el codigo queda en cuenta personal, la continuidad depende de una persona y no de la empresa.',
    ],
    'DEC-145' => [
        'decision' => 'Definir si repositorios, correos, nubes, tableros y cuentas tecnicas deben administrarse mediante cuentas corporativas.',
        'fundamento' => 'Articulos guia: Ley 1581/2012 arts. 4 y 17 sobre seguridad y deberes del responsable; Ley 1273/2009 sobre acceso abusivo, obstaculizacion y uso indebido de sistemas; Ley 222/1995 art. 23 sobre diligencia de administradores.',
        'ejemplo' => 'Todo repositorio, nube, dominio, hosting, API y plataforma critica se administrara desde cuentas corporativas, con usuarios nominales y prohibicion de cuentas personales como propietarias.',
        'claro' => 'Debe quedar claro: cuentas propietarias, usuarios, roles, recuperacion, doble factor, baja de accesos, responsables y monitoreo.',
        'precedente' => 'La cuenta personal como propietaria de un activo corporativo crea riesgo de perdida, extorsion operativa o bloqueo.',
    ],
    'DEC-146' => [
        'decision' => 'Definir a nombre de quien estaran registrados dominios, hosting, certificados, servidores, tiendas de aplicaciones y servicios tecnologicos.',
        'fundamento' => 'Articulos guia: Decision Andina 486/2000 para signos distintivos cuando hay marcas; Ley 1581/2012 si servicios tratan datos; contratos de registro de dominio, hosting y proveedores cloud; Ley 222/1995 art. 23.',
        'ejemplo' => 'Dominios, hosting y cuentas cloud estaran a nombre de la sociedad titular o administradora, con correo corporativo, metodo de pago empresarial y contactos alternos.',
        'claro' => 'Debe quedar claro: titular, registrador, vencimiento, contacto, metodo de pago, DNS, certificados, hosting, respaldos y plan de renovacion.',
        'precedente' => 'Perder un dominio o hosting puede afectar ventas, reputacion, datos y continuidad operativa.',
    ],
    'DEC-147' => [
        'decision' => 'Definir que activos se registraran ante DNDA, SIC u otras autoridades: software, obras, marcas, lemas, patentes, disenos o secretos empresariales documentados.',
        'fundamento' => 'Articulos guia: Ley 23/1982 y Decision Andina 351/1993 para derecho de autor y software; Decision Andina 486/2000 para marcas, patentes, disenos industriales, lemas y secretos empresariales; normas y tramites de DNDA y SIC.',
        'ejemplo' => 'Se registraran marcas y lemas ante SIC; software, manuales y contenidos relevantes ante DNDA; secretos empresariales se protegeran con confidencialidad, acceso restringido y evidencia interna.',
        'claro' => 'Debe quedar claro: activo registrable, titular, autoridad, paises, responsable, renovaciones, vigilancia, oposiciones y soporte.',
        'precedente' => 'No todo derecho nace con el registro, pero registrar facilita prueba, defensa, licenciamiento y venta.',
    ],
    'DEC-148' => [
        'decision' => 'Definir como se controlaran librerias, plantillas, APIs, software libre, software propietario, imagenes, fuentes y componentes de terceros.',
        'fundamento' => 'Articulos guia: Ley 23/1982 sobre derechos de autor; Decision Andina 351/1993; contratos/licencias de software; Ley 1581/2012 si APIs o terceros tratan datos personales; Ley 222/1995 art. 23.',
        'ejemplo' => 'Antes de usar componentes de terceros se registrara licencia, version, restricciones comerciales, obligaciones de atribucion, tratamiento de datos y responsable de actualizaciones.',
        'claro' => 'Debe quedar claro: proveedor, licencia, version, costo, permisos, restricciones, datos, renovacion, soporte, vulnerabilidades y salida.',
        'precedente' => 'Una licencia incompatible puede impedir comercializar el producto o exponer a reclamos de terceros.',
    ],
    'DEC-149' => [
        'decision' => 'Definir plan de continuidad si el ingeniero principal, proveedor, agencia o administrador tecnico se retira o deja de prestar servicios.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre administracion diligente; Ley 1581/2012 art. 17 sobre deberes de seguridad si hay datos personales; Ley 1273/2009 sobre proteccion de informacion y sistemas; contratos civiles/comerciales de prestacion de servicios.',
        'ejemplo' => 'Todo proveedor critico debera entregar documentacion, claves en gestor corporativo, repositorio actualizado, manual de despliegue, respaldos y periodo de transicion.',
        'claro' => 'Debe quedar claro: suplente, documentacion, claves, respaldos, ambientes, soporte, transferencia, penalidades, confidencialidad y propiedad de entregables.',
        'precedente' => 'La dependencia de una sola persona es un riesgo operativo; el protocolo debe exigir continuidad antes de la crisis.',
    ],
    'DEC-150' => [
        'decision' => 'Definir si aplicaciones, marcas, software, metodologias o bases de datos pueden licenciarse, venderse, franquiciarse o explotarse con terceros.',
        'fundamento' => 'Articulos guia: Ley 23/1982 art. 183 sobre transferencia de derechos patrimoniales; Decision Andina 486/2000 sobre licencias y transferencia de propiedad industrial; Ley 1581/2012 para transferencia/transmision de datos; Ley 1258/2008 art. 24 para acuerdos de accionistas sobre explotacion de activos. Guia DNP 2025 de valoracion de propiedad intelectual para emprendedores y mipymes como referencia consultiva para estimar valor economico.',
        'ejemplo' => 'La explotacion comercial de activos tecnologicos requerira titularidad verificada, contrato de licencia o venta, proteccion de datos, metodo de valoracion, precio de mercado y aprobacion del organo competente.',
        'claro' => 'Debe quedar claro: activo, titular, modalidad, territorio, exclusividad, metodo de valoracion, precio, soporte, datos, mantenimiento, garantias y aprobacion.',
        'precedente' => 'Explotar tecnologia sin ordenar titularidad, licencias y datos puede convertir una oportunidad comercial en contingencia juridica.',
    ],
];
$cat15LegalReferences = [
    [
        'norma' => 'Ley 23 de 1982',
        'articulo' => 'Arts. 1, 2, 12 y 183 - Derecho de autor y cesion',
        'texto' => 'Protege obras literarias, cientificas y artisticas, reconoce derechos patrimoniales y regula transferencia de derechos patrimoniales.',
        'uso' => 'Base para software, contenidos, metodologias, cesiones de empleados/contratistas y explotacion comercial.',
    ],
    [
        'norma' => 'Decision Andina 351 de 1993',
        'articulo' => 'Regimen comun sobre derecho de autor',
        'texto' => 'Armoniza proteccion de obras y derechos de autor en la Comunidad Andina.',
        'uso' => 'Complementa software, obras, contenidos y derechos patrimoniales.',
    ],
    [
        'norma' => 'Decision Andina 486 de 2000',
        'articulo' => 'Propiedad industrial',
        'texto' => 'Regula marcas, lemas, patentes, modelos, disenos industriales, secretos empresariales y otros signos o derechos industriales.',
        'uso' => 'Base para marcas, dominios asociados a signos, registros ante SIC, licencias y explotacion.',
    ],
    [
        'norma' => 'Ley 1581 de 2012',
        'articulo' => 'Arts. 4, 5, 9, 17 y 18 - Datos personales',
        'texto' => 'Regula principios, datos sensibles, autorizacion, deberes del responsable y encargado del tratamiento.',
        'uso' => 'Aplica a bases de datos, plataformas, APIs, proveedores cloud, continuidad y transferencia de informacion.',
    ],
    [
        'norma' => 'Ley 1273 de 2009',
        'articulo' => 'Proteccion de informacion y datos',
        'texto' => 'Introduce delitos informaticos y proteccion penal de la informacion y los datos.',
        'uso' => 'Base para control de accesos, repositorios, cuentas, claves, continuidad y seguridad.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 23 y 34 a 38',
        'texto' => 'Regula deberes de administradores y confiabilidad de informacion financiera.',
        'uso' => 'Aplica a custodia, inventario, registro contable y proteccion de activos tecnologicos.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 5, 17 y 24',
        'texto' => 'Permite organizar sociedades, reglas internas y acuerdos de accionistas.',
        'uso' => 'Sirve para definir titularidad por sociedad y explotacion de tecnologia dentro del grupo.',
    ],
    [
        'norma' => 'DNDA / SIC',
        'articulo' => 'Registros de derecho de autor y propiedad industrial',
        'texto' => 'DNDA administra registro de obras y software; SIC administra propiedad industrial como marcas, patentes y disenos.',
        'uso' => 'Soporte practico para decidir que registrar, a nombre de quien y con que renovacion/vigilancia.',
    ],
    [
        'norma' => 'DNP - Guia de valoracion de propiedad intelectual para emprendedores y mipymes',
        'articulo' => 'Guia consultiva 2025',
        'texto' => 'Orienta la gestion estrategica y valoracion economica de activos de propiedad intelectual para emprendimientos y mipymes.',
        'uso' => 'Aplica cuando la familia deba estimar valor de software, marcas, metodologias, patentes, bases de datos, licencias o activos intangibles para venta, aporte, licencia, sucesion, negociacion o reporte patrimonial.',
        'url' => 'https://www.ige.ch/fileadmin/user_upload/recht/entwicklungszusammenarbeit/2025_DNP_GuIa_valoracion_propiedad_intelectual_para_emprendedores_y_mipymes.pdf',
    ],
];
$cat16Academy = [
    'DEC-151' => [
        'decision' => 'Definir si las oportunidades de negocio conocidas por familiares, administradores o empleados deben ofrecerse primero a la empresa o grupo familiar.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre lealtad, interes social, conflictos de interes y abstencion de competir; Decreto 46/2024 reglamenta conflictos de interes y competencia de administradores. Ley 1258/2008 art. 24 sobre acuerdos de accionistas. Ley 256/1996 arts. 8, 15 y 17 sobre desviacion de clientela, explotacion de reputacion ajena e induccion a ruptura contractual.',
        'ejemplo' => 'Toda oportunidad relacionada con el giro actual o plan estrategico de la empresa debera informarse primero a la junta o comite de inversiones antes de ser tomada por un familiar.',
        'claro' => 'Debe quedar claro: que es oportunidad corporativa, quien debe revelarla, plazo de respuesta, rechazo formal, confidencialidad y consecuencia de omitirla.',
        'precedente' => 'La oportunidad de negocio no revelada puede convertirse en conflicto de interes, competencia con la sociedad o desvio de valor familiar.',
    ],
    'DEC-152' => [
        'decision' => 'Definir si familiares pueden crear empresas en sectores similares, complementarios o no relacionados con los negocios familiares.',
        'fundamento' => 'Articulos guia: Constitucion art. 333 sobre libertad economica dentro de limites del bien comun y competencia; Ley 222/1995 art. 23 si el familiar es administrador; Ley 256/1996 sobre competencia desleal; Ley 1258/2008 art. 24 para pactos de accionistas.',
        'ejemplo' => 'Los familiares podran emprender en sectores no competidores; si el sector es similar o complementario, deberan revelar el proyecto, conflictos, clientes objetivo y uso de recursos.',
        'claro' => 'Debe quedar claro: sectores permitidos, sectores restringidos, revelacion previa, no uso de informacion, clientes, proveedores, marca y recursos.',
        'precedente' => 'La libertad de empresa existe, pero no autoriza aprovechar informacion, reputacion o recursos de la empresa familiar en perjuicio de ella.',
    ],
    'DEC-153' => [
        'decision' => 'Definir si se permitiran negocios familiares que compitan directamente con la empresa principal y bajo que restricciones.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 numeral 7 sobre competencia con la sociedad y conflictos de interes; Decreto 46/2024; Ley 256/1996 arts. 7 a 19 sobre actos de competencia desleal; Ley 1258/2008 art. 43 sobre abuso del derecho.',
        'ejemplo' => 'No se permitira competencia directa con la empresa principal por parte de administradores o familiares con informacion sensible, salvo autorizacion expresa y medidas de separacion.',
        'claro' => 'Debe quedar claro: que es competencia directa, personas restringidas, autorizacion, murallas de informacion, clientes prohibidos, proveedores, sanciones y plazo.',
        'precedente' => 'La competencia entre familiares puede ser legitima o destructiva; la diferencia esta en transparencia, autorizacion y ausencia de aprovechamiento indebido.',
    ],
    'DEC-154' => [
        'decision' => 'Definir si un familiar puede usar apellido, marca, historia, reputacion, clientes, instalaciones o imagen familiar en un proyecto propio.',
        'fundamento' => 'Articulos guia: Decision Andina 486/2000 sobre marcas, lemas, nombres comerciales y signos distintivos. Ley 256/1996 art. 15 sobre explotacion de reputacion ajena y art. 10 sobre confusion. Ley 23/1982 si se usan contenidos protegidos. Ley 1581/2012 si se usan bases de datos.',
        'ejemplo' => 'El uso de apellido o marca familiar en emprendimientos propios requerira autorizacion escrita, limites graficos, control de reputacion y prohibicion de inducir confusion con la empresa principal.',
        'claro' => 'Debe quedar claro: signos permitidos, autorizador, territorio, duracion, calidad, revocatoria, uso de clientes/datos y responsabilidad reputacional.',
        'precedente' => 'La reputacion familiar es un activo; su uso individual sin regla puede crear confusion, deterioro de marca o reclamos entre ramas.',
    ],
    'DEC-155' => [
        'decision' => 'Definir si la familia financiara emprendimientos de nuevas generaciones y con que reglas de acceso.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1602 sobre acuerdos validos; Ley 1258/2008 arts. 17 y 24 si se canaliza mediante sociedad o acuerdo de accionistas; Ley 222/1995 art. 23 si se usan recursos sociales; Estatuto Tributario art. 107 si se pretende deducir gastos desde empresa.',
        'ejemplo' => 'La familia podra crear un fondo de emprendimiento financiado con aportes familiares o dividendos distribuidos, no con caja empresarial, salvo aprobacion societaria justificada.',
        'claro' => 'Debe quedar claro: fuente de recursos, beneficiarios, monto, requisitos, comite evaluador, modalidad, seguimiento y conflicto de interes.',
        'precedente' => 'Financiar emprendimientos puede formar nuevas generaciones, pero sin reglas se vuelve subsidio familiar sin rendicion.',
    ],
    'DEC-156' => [
        'decision' => 'Definir que organo evaluara la viabilidad de nuevos proyectos familiares y que informacion minima exigira.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 18 a 24 y 31 sobre informacion, control y protocolo. Ley 222/1995 art. 23 sobre diligencia. Ley 1258/2008 arts. 17 y 24 para organos, comites o acuerdos.',
        'ejemplo' => 'El comite de inversiones evaluara plan de negocio, mercado, riesgos, equipo, presupuesto, propiedad intelectual, conflictos, retorno esperado y escenarios de salida.',
        'claro' => 'Debe quedar claro: organo, criterios, documentos, plazo, evaluadores, recusaciones, aprobacion, seguimiento e informes.',
        'precedente' => 'Un proyecto familiar debe poder pasar preguntas de negocio, no solo entusiasmo o afecto.',
    ],
    'DEC-157' => [
        'decision' => 'Definir si la inversion familiar en emprendimientos sera prestamo, aporte de capital, participacion, donacion, beca o combinacion.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1602; Codigo de Comercio sobre sociedades y aportes cuando aplique; Ley 1258/2008 arts. 5, 9, 10, 17 y 24; Ley 222/1995 art. 23 si hay administradores o recursos sociales.',
        'ejemplo' => 'La familia definira por proyecto si entrega prestamo con tasa, aporte de capital con participacion, nota convertible o apoyo no reembolsable con limites educativos.',
        'claro' => 'Debe quedar claro: modalidad, monto, titularidad, derechos economicos, voto, garantias, plazo, retorno, salida y efecto ante incumplimiento.',
        'precedente' => 'Llamar “ayuda” a lo que era inversion o prestamo crea conflicto al momento de pedir resultados o pago.',
    ],
    'DEC-158' => [
        'decision' => 'Definir limite maximo de riesgo destinado a nuevos emprendimientos, por proyecto, por familiar y por periodo.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre diligencia e interes social. Guia Colombiana Medidas 18 a 24 sobre control y riesgos. Ley 1258/2008 arts. 17 y 24 para pactar politica de inversion.',
        'ejemplo' => 'El fondo destinara maximo el porcentaje definido del patrimonio liquido familiar o dividendos anuales, con tope por proyecto y prohibicion de comprometer activos esenciales.',
        'claro' => 'Debe quedar claro: monto maximo, fuente, concentracion, perdidas toleradas, aprobador, desembolsos por hitos y suspension.',
        'precedente' => 'El limite de riesgo permite apoyar innovacion sin poner en peligro patrimonio construido.',
    ],
    'DEC-159' => [
        'decision' => 'Definir como se trataran perdidas, incumplimientos, fracaso del proyecto o imposibilidad de pago sin romper la relacion familiar.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1602 y reglas de obligaciones; Ley 1258/2008 art. 24 si hay acuerdos; Ley 222/1995 arts. 19 a 21 para actas y art. 23 si involucra sociedad. Guia Colombiana Medidas 28 a 31 sobre manejo preventivo de conflictos.',
        'ejemplo' => 'Si el proyecto fracasa, se hara cierre documentado con informe de aprendizajes, liquidacion, pagos pendientes, manejo de garantias y regla de elegibilidad futura.',
        'claro' => 'Debe quedar claro: perdida asumida, deuda exigible, condonacion, garantias, reporte, plazo, aprendizaje, restricciones y trato familiar.',
        'precedente' => 'El fracaso previsto es manejable; el fracaso sin regla se vuelve deuda emocional y familiar.',
    ],
    'DEC-160' => [
        'decision' => 'Definir cuando un emprendimiento exitoso podra incorporarse al grupo empresarial, comprarse, fusionarse o recibir inversion mayor.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 5, 17, 24, 30 y 31 sobre sociedades, acuerdos, reformas y transformacion. Codigo de Comercio sobre fusiones, escisiones o adquisiciones cuando aplique. Ley 222/1995 art. 23 sobre diligencia, conflicto e interes social.',
        'ejemplo' => 'Un emprendimiento podra incorporarse al grupo si demuestra ventas, rentabilidad o traccion, titularidad clara, ausencia de contingencias, valoracion independiente y aprobacion del organo competente.',
        'claro' => 'Debe quedar claro: criterios de exito, valoracion, debida diligencia, titularidad, precio, forma de pago, gobierno, conflictos y salida del fundador.',
        'precedente' => 'Integrar un emprendimiento familiar al grupo exige tratarlo como operacion entre vinculados: con precio, soporte y aprobacion independiente.',
    ],
];
$cat16LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Art. 333 - Libertad economica y competencia',
        'texto' => 'Reconoce la libertad economica y la iniciativa privada dentro de los limites del bien comun, la responsabilidad social y la libre competencia.',
        'uso' => 'Base para permitir emprendimientos sin desconocer limites de competencia leal e interes empresarial.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Art. 23 - Deberes y conflictos de administradores',
        'texto' => 'Exige buena fe, lealtad, diligencia, interes social y abstencion en conflictos de interes o competencia con la sociedad salvo autorizacion expresa.',
        'uso' => 'Base central para oportunidades de negocio, competencia, uso de recursos y operaciones con emprendimientos familiares.',
    ],
    [
        'norma' => 'Decreto 46 de 2024',
        'articulo' => 'Conflictos de interes y competencia',
        'texto' => 'Reglamenta parcialmente el art. 23 de la Ley 222 en materia de conflictos de interes y actos de competencia de administradores.',
        'uso' => 'Aplica a revelacion, autorizacion y abstencion cuando el emprendimiento involucre administradores.',
    ],
    [
        'norma' => 'Ley 256 de 1996',
        'articulo' => 'Arts. 8, 10, 15 y 17 - Competencia desleal',
        'texto' => 'Regula desviacion de clientela, confusion, explotacion de reputacion ajena e induccion a ruptura contractual, entre otros actos.',
        'uso' => 'Base para restricciones de competencia, uso de marca familiar, clientes, proveedores y reputacion.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 5, 9, 10, 17, 24, 30 y 31',
        'texto' => 'Regula constitucion, capital, clases de acciones, organizacion, acuerdos, reformas y transformaciones de S.A.S.',
        'uso' => 'Aplica a financiacion, participacion, integracion al grupo, acuerdos de accionistas y nuevas sociedades.',
    ],
    [
        'norma' => 'Decision Andina 486 de 2000 / Ley 23 de 1982',
        'articulo' => 'Signos distintivos y derecho de autor',
        'texto' => 'Protegen marcas, nombres comerciales, lemas, obras, contenidos y otros activos intangibles.',
        'uso' => 'Aplica a uso de marca familiar, apellido, reputacion, contenidos y activos tecnologicos en emprendimientos.',
    ],
    [
        'norma' => 'Ley 1581 de 2012',
        'articulo' => 'Arts. 4, 9 y 17 - Datos personales',
        'texto' => 'Regula principios, autorizacion y deberes del responsable de tratamiento de datos.',
        'uso' => 'Aplica si el emprendimiento usa bases de clientes, contactos o datos de empresas/familia.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Art. 1602 - Fuerza obligatoria del contrato',
        'texto' => 'Los contratos validamente celebrados obligan a quienes los suscriben.',
        'uso' => 'Base para prestamos, aportes, contratos de inversion, notas convertibles y pactos de salida.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 18 a 24, 28 a 32',
        'texto' => 'Recomienda informacion, control, riesgos, organos de familia, protocolo y separacion de roles.',
        'uso' => 'Marco consultivo para evaluar proyectos, limites de riesgo y gobierno de emprendimientos familiares.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$cat17Academy = [
    'DEC-161' => [
        'decision' => 'Definir comportamientos minimos exigidos a miembros de la familia frente a empresa, patrimonio, informacion, colaboradores y otros familiares.',
        'fundamento' => 'Articulos guia: Constitucion arts. 15 y 42 sobre intimidad, buen nombre y familia. Ley 222/1995 art. 23 sobre buena fe, lealtad y diligencia si hay administradores. Guia Colombiana Medidas 31 y 32 sobre protocolo, roles y limites familiares.',
        'ejemplo' => 'La familia adoptara un codigo de conducta basado en respeto, confidencialidad, transparencia, no agresion, no uso indebido de activos, declaracion de conflictos y cumplimiento de acuerdos.',
        'claro' => 'Debe quedar claro: conductas esperadas, conductas prohibidas, responsables, consecuencias, canal de reporte y forma de actualizacion.',
        'precedente' => 'Un codigo claro evita que las reglas dependan del temperamento de cada reunion o de la autoridad informal de una persona.',
    ],
    'DEC-162' => [
        'decision' => 'Definir que situaciones deben declararse como conflictos de interes reales, potenciales o aparentes.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 numeral 7 sobre conflictos de interes y competencia con la sociedad; Decreto 46/2024 sobre conflictos de interes de administradores; Guia Supersociedades de conflictos de intereses; Ley 1258/2008 art. 43 sobre abuso del derecho.',
        'ejemplo' => 'Se declararan conflictos cuando un familiar, administrador o vinculado tenga interes personal en contratos, empleo, prestamos, garantias, compras, ventas, inversiones o uso de activos.',
        'claro' => 'Debe quedar claro: que es conflicto, quien declara, cuando, formato, organo que decide, abstencion, registro y consecuencias de ocultarlo.',
        'precedente' => 'El conflicto no siempre es prohibido; lo grave es ocultarlo o decidir sin autorizacion independiente.',
    ],
    'DEC-163' => [
        'decision' => 'Definir cuando un familiar debe abstenerse de votar, opinar, contratar, evaluar o participar en una decision.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 numeral 7 exige abstencion en conflictos salvo autorizacion expresa; Decreto 46/2024; Ley 1258/2008 art. 43 sobre abuso de voto. Ley 222/1995 art. 21 para dejar acta.',
        'ejemplo' => 'El familiar interesado no participara en decisiones sobre su empleo, remuneracion, contrato, prestamo, sancion, compra de acciones o negocio propio relacionado.',
        'claro' => 'Debe quedar claro: causales de abstencion, si puede dar informacion, quien decide, quorum sin interesado, acta y sancion por no abstenerse.',
        'precedente' => 'La abstencion protege la validez y legitimidad de la decision; no es un castigo al familiar conflictuado.',
    ],
    'DEC-164' => [
        'decision' => 'Definir el primer procedimiento para resolver desacuerdos: conversacion directa, reunion facilitada, consejo de familia o comite de convivencia.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 19 a 21 para documentar reuniones y acuerdos; Guia Colombiana Medidas 28 a 31 sobre organos de familia y protocolo. Codigo Civil art. 1602 sobre acuerdos validos.',
        'ejemplo' => 'Todo desacuerdo iniciara con conversacion directa y registro de acuerdos; si no se resuelve en 15 dias, pasara al consejo de familia con facilitador.',
        'claro' => 'Debe quedar claro: etapas, plazos, convocante, participantes, confidencialidad, acta, pendientes y escalamiento.',
        'precedente' => 'Resolver temprano evita que diferencias operativas se conviertan en conflictos patrimoniales o societarios.',
    ],
    'DEC-165' => [
        'decision' => 'Definir si se acudira a mediacion o conciliacion antes de iniciar procesos judiciales o arbitrales.',
        'fundamento' => 'Articulos guia: Ley 1563/2012 sobre arbitraje y amigable composicion; Ley 222/1995 arts. 19 a 21 para soportar acuerdos; Ley 1258/2008 art. 40 sobre conflictos societarios de S.A.S. cuando aplique. Normas de conciliacion aplicables segun materia.',
        'ejemplo' => 'Antes de demanda o arbitraje, las partes acudiran a mediacion privada o centro de conciliacion, salvo urgencia por medidas cautelares o proteccion de activos.',
        'claro' => 'Debe quedar claro: mediador, centro, plazo, costos, confidencialidad, obligatoriedad, excepciones y efectos del acuerdo.',
        'precedente' => 'La mediacion preserva relacion familiar incluso cuando no logra acuerdo total; ayuda a ordenar posiciones y pruebas.',
    ],
    'DEC-166' => [
        'decision' => 'Definir que controversias se someteran a tribunal de arbitramento, amigable composicion, juez ordinario o Supersociedades.',
        'fundamento' => 'Articulos guia: Ley 1563/2012 sobre arbitraje nacional e internacional y amigable composicion. Ley 1258/2008 art. 40 sobre resolucion de conflictos societarios y art. 24 sobre acuerdos de accionistas. Codigo General del Proceso segun controversia.',
        'ejemplo' => 'Las controversias sobre acuerdos de accionistas, valoracion, compraventa o incumplimiento patrimonial se someteran a arbitraje; conflictos familiares no patrimoniales iran primero a mediacion.',
        'claro' => 'Debe quedar claro: materias, sede, centro, numero de arbitros, derecho aplicable, costos, idioma, medidas urgentes y relacion con estatutos.',
        'precedente' => 'La clausula de solucion de controversias debe ser precisa; una clausula confusa produce otro conflicto sobre donde resolver el conflicto.',
    ],
    'DEC-167' => [
        'decision' => 'Definir consecuencias por incumplir el protocolo, acuerdos de confidencialidad, conflictos de interes, asistencia, pagos o reglas de conducta.',
        'fundamento' => 'Articulos guia: Codigo Civil art. 1602 y reglas de responsabilidad contractual; Ley 1258/2008 art. 24 si el acuerdo es de accionistas; Ley 222/1995 art. 24 sobre responsabilidad de administradores; Constitucion art. 29 como referente de debido proceso.',
        'ejemplo' => 'Las consecuencias podran incluir amonestacion, perdida temporal de beneficios no adquiridos, obligacion de reparar, exclusion de comites o activacion de venta pactada, con debido proceso.',
        'claro' => 'Debe quedar claro: incumplimientos, gravedad, procedimiento, defensa, autoridad, proporcionalidad, reparacion, apelacion y limites legales.',
        'precedente' => 'Una sancion sin debido proceso puede ser peor que no tener sancion: aumenta el conflicto y expone el acuerdo.',
    ],
    'DEC-168' => [
        'decision' => 'Definir que informacion familiar y empresarial se considera reservada y como se protege.',
        'fundamento' => 'Articulos guia: Constitucion art. 15 sobre intimidad, buen nombre y habeas data. Ley 1581/2012 arts. 4, 5, 9, 17 y 18 sobre datos personales. Ley 222/1995 art. 23 sobre reserva comercial e informacion de la sociedad. Ley 1273/2009 sobre proteccion de informacion y datos.',
        'ejemplo' => 'Seran reservados estados financieros no publicos, estrategias, datos familiares, datos de clientes, claves, contratos, valoraciones, conflictos, actas y documentos patrimoniales.',
        'claro' => 'Debe quedar claro: informacion reservada, autorizados, medios, plazo, excepciones, sanciones, tratamiento de datos y devolucion/destruccion.',
        'precedente' => 'La confianza familiar no sustituye acuerdos de confidencialidad; la informacion sensible debe tener regla escrita.',
    ],
    'DEC-169' => [
        'decision' => 'Definir si existira canal confidencial para reportar irregularidades, incumplimientos, conflictos, acoso, uso indebido de activos o riesgos.',
        'fundamento' => 'Articulos guia: Ley 1581/2012 sobre datos personales y confidencialidad del reporte; Ley 222/1995 art. 23 sobre deberes de administradores; Guia Colombiana Medidas 18 a 24 sobre control y transparencia. Normas laborales sobre convivencia cuando aplique a trabajadores.',
        'ejemplo' => 'La familia tendra canal de reporte administrado por secretario del consejo o tercero, con confidencialidad, no represalia, trazabilidad y cierre documentado.',
        'claro' => 'Debe quedar claro: canal, responsable, anonimato o reserva, no represalia, investigacion, tiempos, evidencia, cierre y archivo.',
        'precedente' => 'Un canal serio permite corregir antes de que el problema salga por vias destructivas o publicas.',
    ],
    'DEC-170' => [
        'decision' => 'Definir como se trataran agresiones, hostigamientos, amenazas, ataques publicos, discriminacion o afectaciones a la convivencia familiar.',
        'fundamento' => 'Articulos guia: Constitucion arts. 13, 15, 21 y 42 sobre igualdad, intimidad, honra y familia. Ley 1010/2006 si la conducta ocurre en contexto laboral como acoso laboral. Codigo Penal si existen amenazas, injurias, calumnias u otras conductas punibles. Guia Colombiana Medidas 28 a 31.',
        'ejemplo' => 'Las agresiones o ataques entre familiares activaran ruta de contencion, registro, mediacion, medidas de proteccion, disculpa/reparacion y, si aplica, remision legal o laboral.',
        'claro' => 'Debe quedar claro: conductas prohibidas, ruta urgente, proteccion, prueba, confidencialidad, autoridad, consecuencias y apoyo psicosocial o legal.',
        'precedente' => 'La convivencia tambien es patrimonio: si se deteriora, arrastra empresa, propiedad y sucesion.',
    ],
];
$cat17LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Arts. 13, 15, 21, 29 y 42',
        'texto' => 'Protegen igualdad, intimidad, buen nombre, honra, debido proceso y familia.',
        'uso' => 'Base para conducta, confidencialidad, sanciones, respeto y convivencia.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 19 a 24',
        'texto' => 'Regulan reuniones, actas, administradores, deberes, conflictos de interes y responsabilidad.',
        'uso' => 'Aplica a conflictos de interes, abstencion, actas, deber de reserva y sanciones de administradores.',
    ],
    [
        'norma' => 'Decreto 46 de 2024',
        'articulo' => 'Conflictos de interes y competencia',
        'texto' => 'Reglamenta parcialmente el art. 23 de la Ley 222 sobre conflictos de interes y actos de competencia de administradores.',
        'uso' => 'Base practica para revelar conflictos, abstenerse y obtener autorizacion expresa.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 24, 40 y 43',
        'texto' => 'Regula acuerdos de accionistas, resolucion de conflictos societarios y abuso del derecho de voto.',
        'uso' => 'Aplica a controversias entre accionistas, acuerdos, bloqueos, arbitraje y abuso.',
    ],
    [
        'norma' => 'Ley 1563 de 2012',
        'articulo' => 'Arbitraje y amigable composicion',
        'texto' => 'Regula arbitraje nacional e internacional y amigable composicion.',
        'uso' => 'Base para clausula arbitral, amigable composicion y solucion de controversias patrimoniales.',
    ],
    [
        'norma' => 'Ley 1581 de 2012',
        'articulo' => 'Arts. 4, 5, 9, 17 y 18',
        'texto' => 'Regula principios, datos sensibles, autorizacion y deberes del responsable/encargado de tratamiento.',
        'uso' => 'Aplica a confidencialidad, canal de denuncias, actas, datos familiares y datos empresariales personales.',
    ],
    [
        'norma' => 'Ley 1273 de 2009',
        'articulo' => 'Proteccion de informacion y datos',
        'texto' => 'Protege penalmente informacion, datos y sistemas informaticos frente a accesos o usos indebidos.',
        'uso' => 'Aplica a confidencialidad, datos, claves, documentos y canales digitales.',
    ],
    [
        'norma' => 'Ley 1010 de 2006',
        'articulo' => 'Acoso laboral',
        'texto' => 'Regula medidas para prevenir, corregir y sancionar acoso laboral cuando exista contexto de trabajo.',
        'uso' => 'Aplica si agresiones o hostigamientos ocurren en empresa o relacion laboral.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 18 a 24, 28 a 32',
        'texto' => 'Recomienda control, transparencia, organos de familia, protocolo y roles familiares.',
        'uso' => 'Marco consultivo para codigo de conducta, canal de reporte y manejo preventivo de conflictos.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
    [
        'norma' => 'Guia Supersociedades',
        'articulo' => 'Gestion de conflictos de intereses',
        'texto' => 'Explica revelacion, abstencion y autorizacion en situaciones de conflicto de intereses.',
        'uso' => 'Documento consultivo para DEC-162 y DEC-163.',
        'url' => 'https://www.supersociedades.gov.co/documents/20122/1229078/GUIA-GESTION-CONFLICTO-INTERESES.pdf',
    ],
];
$cat18Academy = [
    'DEC-171' => [
        'decision' => 'Definir educacion financiera, patrimonial y empresarial que recibiran miembros de la familia segun edad, rol y participacion.',
        'fundamento' => 'Articulos guia: Constitucion arts. 42, 44 y 67 sobre familia, derechos de ninos y educacion. Guia Colombiana Medidas 28 a 32 sobre organos de familia, protocolo y roles. Ley 1581/2012 arts. 4, 9 y 17 si se tratan datos de familiares o menores.',
        'ejemplo' => 'La familia tendra programa anual de educacion financiera con modulos de presupuesto, patrimonio, sociedades, impuestos, riesgos, inversiones y lectura de estados financieros.',
        'claro' => 'Debe quedar claro: contenidos, edades, responsables, presupuesto, asistencia, evaluacion, datos tratados y evidencias de participacion.',
        'precedente' => 'La educacion patrimonial reduce improvisacion y prepara propietarios responsables antes de que reciban derechos economicos o politicos.',
    ],
    'DEC-172' => [
        'decision' => 'Definir como se transmitira la historia de fundadores, empresas, valores, aprendizajes, errores y legado familiar.',
        'fundamento' => 'Articulos guia: Constitucion art. 42 sobre familia. Ley 222/1995 art. 21 sobre actas y soporte documental cuando haya decisiones. Ley 1581/2012 art. 4 y 9 si se tratan imagenes, relatos o datos personales. Guia Colombiana Medida 31 sobre protocolo de familia.',
        'ejemplo' => 'Se conservara archivo familiar con historia, entrevistas, fotografias autorizadas, hitos empresariales, valores fundacionales y lecciones para nuevas generaciones.',
        'claro' => 'Debe quedar claro: contenidos, custodio, autorizaciones de imagen/datos, acceso, actualizacion, confidencialidad y uso pedagogico.',
        'precedente' => 'La historia familiar bien documentada convierte memoria en criterio; sin documentarla, se vuelve relato fragmentado.',
    ],
    'DEC-173' => [
        'decision' => 'Definir a que edad descendientes pueden asistir a reuniones familiares, con que voz, informacion y restricciones.',
        'fundamento' => 'Articulos guia: Constitucion art. 44 sobre derechos prevalentes de ninos y art. 42 sobre familia. Ley 1098/2006 sobre proteccion integral de ninos, ninas y adolescentes. Ley 1581/2012 arts. 5, 7, 9 y 17 sobre datos sensibles y datos de menores.',
        'ejemplo' => 'Los descendientes podran asistir a actividades pedagogicas desde los 12 anos, con informacion adaptada; la participacion con voz en asamblea familiar iniciara a los 18 anos.',
        'claro' => 'Debe quedar claro: edad, tipo de reunion, informacion permitida, autorizacion de padres, confidencialidad, voz, voto y acompaniamiento.',
        'precedente' => 'Incluir temprano no significa exponer informacion sensible sin madurez ni autorizacion.',
    ],
    'DEC-174' => [
        'decision' => 'Definir si existira programa permanente de formacion familiar con calendario, presupuesto y responsables.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28 a 32 sobre organos de familia, protocolo y roles. Ley 222/1995 art. 23 si se usan recursos sociales y debe justificarse interes de la sociedad. Ley 1258/2008 arts. 17 y 24 para formalizar reglas.',
        'ejemplo' => 'El consejo de familia aprobara un plan anual de formacion con sesiones de gobierno familiar, empresa, patrimonio, sucesion, tecnologia, convivencia y liderazgo.',
        'claro' => 'Debe quedar claro: temas, calendario, presupuesto, asistentes, instructores, evaluacion, certificados y responsable de seguimiento.',
        'precedente' => 'La continuidad generacional requiere sistema, no solo charlas ocasionales cuando aparece un problema.',
    ],
    'DEC-175' => [
        'decision' => 'Definir si jovenes pueden realizar practicas empresariales en empresas familiares y bajo que condiciones laborales, formativas y de seguridad.',
        'fundamento' => 'Articulos guia: Codigo Sustantivo del Trabajo art. 23 si hay relacion laboral real; Ley 789/2002 y normas de contrato de aprendizaje cuando aplique; Constitucion art. 53 sobre principios laborales; Ley 1098/2006 si participan menores.',
        'ejemplo' => 'Las practicas seran formativas, con plan de aprendizaje, tutor, duracion definida, reglas de seguridad, confidencialidad y sin promesa automatica de empleo.',
        'claro' => 'Debe quedar claro: edad, tipo de practica, contrato o convenio, tutor, remuneracion/apoyo, horario, seguridad social, confidencialidad y evaluacion.',
        'precedente' => 'La practica debe formar; si existe subordinacion y remuneracion real, puede convertirse en relacion laboral.',
    ],
    'DEC-176' => [
        'decision' => 'Definir si nuevas generaciones tendran mentor familiar o externo para orientar desarrollo patrimonial, profesional y humano.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28 a 32. Ley 1581/2012 arts. 4, 9 y 17 si se documentan planes personales o datos de menores. Constitucion art. 67 sobre educacion y formacion.',
        'ejemplo' => 'Cada joven interesado tendra mentor por ciclos de un ano, con objetivos de aprendizaje, reuniones trimestrales, confidencialidad y reporte general sin divulgar intimidad.',
        'claro' => 'Debe quedar claro: mentor, criterios, duracion, temas, limites, confidencialidad, reporte, conflicto de interes y reemplazo.',
        'precedente' => 'La mentoria ordena la transmision de experiencia sin convertirla en presion o favoritismo.',
    ],
    'DEC-177' => [
        'decision' => 'Definir como se identificaran y desarrollaran futuros lideres familiares, empresariales, patrimoniales o sociales.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28 a 32 sobre roles y organos de familia. Ley 222/1995 arts. 22, 23 y 24 si el liderazgo implica administracion. Constitucion art. 13 sobre igualdad y no discriminacion.',
        'ejemplo' => 'El liderazgo se desarrollara mediante formacion, evaluacion de competencias, participacion gradual, proyectos reales y retroalimentacion de familiares e independientes.',
        'claro' => 'Debe quedar claro: criterios, proceso, igualdad de oportunidades, evaluadores, rutas de liderazgo, limites y diferencia entre liderazgo familiar y cargo empresarial.',
        'precedente' => 'Liderazgo familiar no debe heredarse automaticamente; debe prepararse, reconocerse y validarse.',
    ],
    'DEC-178' => [
        'decision' => 'Definir preparacion minima antes de ejercer derechos politicos como accionista o representante de rama.',
        'fundamento' => 'Articulos guia: Codigo de Comercio art. 379 sobre derechos de cada accion; Ley 1258/2008 art. 24 sobre acuerdos de accionistas; Guia Colombiana Medidas 31 y 32. Ley 1581/2012 si se maneja informacion reservada.',
        'ejemplo' => 'Antes de ejercer voto coordinado o representar una rama, el familiar debera completar formacion en estados financieros, estatutos, protocolo, conflictos de interes y deber de confidencialidad.',
        'claro' => 'Debe quedar claro: formacion exigida, evidencia, excepciones, informacion accesible, confidencialidad y apoyo a nuevos accionistas.',
        'precedente' => 'Votar sin comprender aumenta el riesgo de decisiones emocionales o capturadas por terceros.',
    ],
    'DEC-179' => [
        'decision' => 'Definir como aumentara gradualmente la participacion de jovenes en asamblea familiar, consejo, comites, proyectos y observacion de organos.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 28 a 32. Ley 222/1995 arts. 19 a 21 para actas cuando participen en reuniones formales. Ley 1258/2008 arts. 17 y 24 si se pactan reglas de participacion.',
        'ejemplo' => 'La participacion sera por etapas: observador, participante con voz, miembro de comite, representante suplente y eventual miembro pleno segun edad, formacion y evaluacion.',
        'claro' => 'Debe quedar claro: etapas, requisitos, edad, voz, voto, confidencialidad, mentor, evaluacion y causales de suspension.',
        'precedente' => 'La participacion gradual evita saltos bruscos de responsabilidad y permite aprender sin poner en riesgo decisiones criticas.',
    ],
    'DEC-180' => [
        'decision' => 'Definir si la familia financiara estudios, cursos o programas y que compromisos tendra el beneficiario.',
        'fundamento' => 'Articulos guia: Constitucion art. 67 sobre educacion. Codigo Civil art. 1602 si se pacta beca, prestamo condonable o compromiso. Ley 222/1995 art. 23 si se usan recursos de empresa; Estatuto Tributario art. 107 si se busca deducibilidad empresarial.',
        'ejemplo' => 'La familia podra financiar estudios mediante beca, prestamo condonable o apoyo parcial, sujeto a rendimiento, permanencia, reporte y contribucion posterior al plan familiar.',
        'claro' => 'Debe quedar claro: beneficiarios, criterios, monto, fuente, modalidad, compromisos, incumplimiento, confidencialidad y rendicion.',
        'precedente' => 'La financiacion educativa debe ser inversion formativa, no privilegio sin reglas ni retorno de aprendizaje.',
    ],
];
$cat18LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Arts. 13, 42, 44, 53 y 67',
        'texto' => 'Regulan igualdad, familia, derechos de ninos, principios laborales y derecho a la educacion.',
        'uso' => 'Base para inclusion generacional, formacion, practicas, liderazgo y financiacion educativa.',
    ],
    [
        'norma' => 'Ley 1098 de 2006',
        'articulo' => 'Codigo de Infancia y Adolescencia',
        'texto' => 'Establece proteccion integral y derechos de ninos, ninas y adolescentes.',
        'uso' => 'Aplica a edad de incorporacion, practicas de menores, tratamiento de informacion y participacion gradual.',
    ],
    [
        'norma' => 'Ley 1581 de 2012',
        'articulo' => 'Arts. 4, 5, 7, 9, 17 y 18',
        'texto' => 'Regula principios, datos sensibles, datos de menores, autorizacion y deberes de responsables/encargados.',
        'uso' => 'Aplica a datos de familiares, menores, historia familiar, mentoria y registros de formacion.',
    ],
    [
        'norma' => 'Codigo Sustantivo del Trabajo / Ley 789 de 2002',
        'articulo' => 'Practicas, aprendizaje y relacion laboral',
        'texto' => 'Permiten diferenciar practicas formativas, contrato de aprendizaje y contrato laboral segun condiciones reales.',
        'uso' => 'Base de practicas empresariales de jovenes y no promesa automatica de empleo.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 19 a 24',
        'texto' => 'Regula actas, administradores, deberes y responsabilidad.',
        'uso' => 'Aplica cuando jovenes participan en organos, se usan recursos sociales o se preparan futuros administradores.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 17 y 24',
        'texto' => 'Permite organizar reglas internas y acuerdos de accionistas.',
        'uso' => 'Sirve para formalizar participacion gradual, formacion de accionistas y reglas de gobierno familiar.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Art. 379 - Derechos de accion',
        'texto' => 'Reconoce derechos de los accionistas segun las acciones que poseen.',
        'uso' => 'Base para preparar a nuevas generaciones antes de ejercer derechos politicos.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Art. 1602 - Fuerza obligatoria del contrato',
        'texto' => 'Los contratos validamente celebrados obligan a quienes los suscriben.',
        'uso' => 'Base para becas, prestamos educativos, compromisos de beneficiarios y acuerdos de formacion.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 28 a 32',
        'texto' => 'Recomienda organos de familia, protocolo, roles y limites de familiares.',
        'uso' => 'Marco consultivo principal para plan de formacion familiar y nuevas generaciones.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$cat19Academy = [
    'DEC-181' => [
        'decision' => 'Definir quien puede hablar publicamente en nombre de la familia, las empresas, el grupo o los accionistas familiares.',
        'fundamento' => 'Articulos guia: Constitucion arts. 15, 20 y 21 sobre intimidad, buen nombre, libertad de expresion y honra. Ley 222/1995 art. 23 sobre deber de reserva e interes social de administradores. Ley 1258/2008 art. 24 si se pacta voceria entre accionistas.',
        'ejemplo' => 'La voceria publica correspondera al representante legal para asuntos empresariales y al vocero familiar designado para asuntos de familia, previa coordinacion en crisis.',
        'claro' => 'Debe quedar claro: voceros, temas autorizados, suplentes, medios, autorizaciones previas, crisis y prohibicion de hablar por la familia sin mandato.',
        'precedente' => 'Una sola declaracion improvisada puede afectar reputacion, clientes, empleados, bancos y unidad familiar.',
    ],
    'DEC-182' => [
        'decision' => 'Definir reglas de redes sociales cuando publicaciones personales puedan afectar reputacion familiar o empresarial.',
        'fundamento' => 'Articulos guia: Constitucion arts. 15, 20 y 21; Ley 1581/2012 arts. 4, 9 y 17 si se publican datos personales; Ley 222/1995 art. 23 sobre reserva e interes social; Ley 256/1996 si hay actos de descrédito o confusion competitiva.',
        'ejemplo' => 'Los familiares evitaran publicar informacion reservada, conflictos internos, estados financieros, imagenes sin autorizacion o mensajes que parezcan voceria oficial.',
        'claro' => 'Debe quedar claro: contenidos prohibidos, autorizaciones, manejo de imagenes, datos personales, crisis, rectificacion y consecuencias.',
        'precedente' => 'La libertad de expresion no elimina deberes de reserva, buen nombre y proteccion de datos.',
    ],
    'DEC-183' => [
        'decision' => 'Definir que informacion de empresas, patrimonio, familia y decisiones puede divulgarse y cual permanece reservada.',
        'fundamento' => 'Articulos guia: Constitucion art. 15; Ley 1581/2012 arts. 4, 5, 9, 17 y 18; Ley 222/1995 art. 23 sobre reserva comercial e informacion societaria; Ley 1273/2009 sobre proteccion de informacion y datos.',
        'ejemplo' => 'Solo se podra divulgar informacion previamente aprobada: historia institucional, logros publicos, datos comerciales no sensibles y proyectos sociales autorizados.',
        'claro' => 'Debe quedar claro: informacion publica, reservada y confidencial; aprobador; canales; datos personales; terceros; sanciones y archivo de autorizaciones.',
        'precedente' => 'La informacion patrimonial y familiar no debe circular por costumbre; requiere criterio, finalidad y autorizacion.',
    ],
    'DEC-184' => [
        'decision' => 'Definir protocolo de actuacion ante noticias, denuncias, rumores, investigaciones, accidentes o controversias publicas.',
        'fundamento' => 'Articulos guia: Constitucion arts. 15, 20 y 21; Ley 222/1995 arts. 23 y 24 sobre diligencia y responsabilidad de administradores; Guia Colombiana Medidas 18 a 24 sobre control, informacion y riesgos.',
        'ejemplo' => 'Ante crisis reputacional se activara comite de crisis con vocero, asesor legal, comunicador, matriz de hechos, mensajes aprobados y plan de seguimiento.',
        'claro' => 'Debe quedar claro: quien convoca, primeras 24 horas, vocero, mensajes, asesores, evidencias, redes sociales, empleados y cierre.',
        'precedente' => 'En crisis, la velocidad sin control es riesgo; el silencio sin estrategia tambien.',
    ],
    'DEC-185' => [
        'decision' => 'Definir quien autoriza el uso del apellido familiar, marcas empresariales, logos, historia, imagenes o reputacion en proyectos, eventos o comunicaciones.',
        'fundamento' => 'Articulos guia: Decision Andina 486/2000 sobre marcas, lemas y signos distintivos. Ley 23/1982 sobre obras e imagenes/contenidos protegidos. Ley 256/1996 art. 15 sobre explotacion de reputacion ajena y art. 10 sobre confusion. Ley 1581/2012 si hay datos o imagenes personales.',
        'ejemplo' => 'El uso de marca, apellido o historia familiar requerira autorizacion escrita del organo definido, condiciones de calidad, duracion, territorio y revocatoria.',
        'claro' => 'Debe quedar claro: signos protegidos, titular, usos permitidos, usos prohibidos, autorizador, control de calidad, revocatoria y sanciones.',
        'precedente' => 'La marca familiar puede construir confianza o generar confusion si cada miembro la usa por separado.',
    ],
    'DEC-186' => [
        'decision' => 'Definir compromisos sociales, comunitarios, eticos o filantropicos que asumira la familia o el grupo empresarial.',
        'fundamento' => 'Articulos guia: Constitucion art. 333 sobre funcion social de la empresa. Guia Colombiana Medidas 18 a 24 y 31 sobre sostenibilidad, informacion y protocolo. Ley 222/1995 art. 23 si administradores destinan recursos sociales.',
        'ejemplo' => 'La familia priorizara educacion, empleo local, apoyo comunitario y sostenibilidad ambiental, con proyectos medibles y presupuesto aprobado.',
        'claro' => 'Debe quedar claro: causas prioritarias, presupuesto, fuente, beneficiarios, aprobador, medicion, conflictos y comunicacion.',
        'precedente' => 'La responsabilidad social debe ser coherente con valores y capacidad real, no solo buena intencion.',
    ],
    'DEC-187' => [
        'decision' => 'Definir porcentaje, fuente, criterios y aprobacion de donaciones familiares o empresariales.',
        'fundamento' => 'Articulos guia: Estatuto Tributario arts. 125-1, 125-5, 257 y 258 sobre donaciones y descuentos tributarios segun beneficiario y requisitos; Decreto 2150/2017 sobre regimen tributario especial; Ley 222/1995 art. 23 si se usan recursos sociales.',
        'ejemplo' => 'Las donaciones empresariales requeriran presupuesto, beneficiario habilitado, certificado, soporte tributario y aprobacion; las donaciones familiares se financiaran con recursos familiares.',
        'claro' => 'Debe quedar claro: porcentaje, fuente, beneficiarios, requisitos, certificados, aprobador, conflicto de interes, seguimiento e impacto.',
        'precedente' => 'La donacion sin soporte puede perder beneficio tributario y convertirse en gasto no justificado.',
    ],
    'DEC-188' => [
        'decision' => 'Definir si se creara fundacion, corporacion, fondo, comite o vehiculo para actividades sociales familiares.',
        'fundamento' => 'Articulos guia: Constitucion art. 38 sobre derecho de asociacion. Regimen de entidades sin animo de lucro y Decreto 2150/2017 para regimen tributario especial cuando aplique. Estatuto Tributario arts. 19, 125-1, 257 y relacionados. Ley 222/1995 art. 23 si participa la sociedad.',
        'ejemplo' => 'La familia evaluara crear fundacion familiar solo si hay objeto claro, gobierno, presupuesto, cumplimiento tributario, informes y separacion de recursos empresariales.',
        'claro' => 'Debe quedar claro: tipo de vehiculo, objeto, gobierno, aportes, control, cumplimiento, beneficiarios, conflictos y reportes.',
        'precedente' => 'Una fundacion sin gobierno y cumplimiento puede generar mas carga que impacto.',
    ],
    'DEC-189' => [
        'decision' => 'Definir criterios para aprobar patrocinios, apoyos, eventos, campanas o asociaciones con terceros.',
        'fundamento' => 'Articulos guia: Estatuto Tributario art. 107 sobre causalidad, necesidad y proporcionalidad de expensas; Ley 222/1995 art. 23 sobre interes social; Ley 1581/2012 si se tratan datos; Ley 256/1996 si hay riesgo reputacional o competencia.',
        'ejemplo' => 'Los patrocinios requeriran alineacion con valores, presupuesto, contrato, contraprestacion, analisis reputacional y prohibicion de favorecer intereses personales no revelados.',
        'claro' => 'Debe quedar claro: criterios, presupuesto, aprobador, contrato, beneficiario, contraprestacion, medicion, conflicto y comunicacion.',
        'precedente' => 'Patrocinar sin criterio puede parecer apoyo social, pero terminar siendo gasto personal o reputacionalmente riesgoso.',
    ],
    'DEC-190' => [
        'decision' => 'Definir principios ambientales, sociales y de conducta empresarial obligatorios para familia, empresas y proyectos.',
        'fundamento' => 'Articulos guia: Constitucion arts. 79, 80 y 333 sobre ambiente, deber estatal/privado y funcion social de la empresa. Ley 99/1993 sobre politica ambiental. Guia Colombiana Medidas 18 a 24 sobre riesgos, control e informacion.',
        'ejemplo' => 'La familia adoptara principios de cumplimiento legal, no corrupcion, respeto laboral, cuidado ambiental, proteccion de datos, relacion honesta con comunidades y proveedores.',
        'claro' => 'Debe quedar claro: principios, responsables, indicadores, prohibiciones, reportes, proveedores, proyectos y consecuencias.',
        'precedente' => 'Sostenibilidad no es decoracion reputacional; es criterio de permanencia y gestion de riesgo.',
    ],
];
$cat19LegalReferences = [
    [
        'norma' => 'Constitucion Politica',
        'articulo' => 'Arts. 15, 20, 21, 38, 79, 80 y 333',
        'texto' => 'Protegen intimidad, buen nombre, libertad de expresion, asociacion, ambiente y funcion social de la empresa.',
        'uso' => 'Base para voceria, redes, reputacion, fundacion familiar, responsabilidad social y sostenibilidad.',
    ],
    [
        'norma' => 'Ley 1581 de 2012 / Ley 1273 de 2009',
        'articulo' => 'Datos personales e informacion',
        'texto' => 'Regulan tratamiento de datos personales y proteccion de informacion y datos.',
        'uso' => 'Aplica a informacion publica, redes, imagenes, crisis y bases de datos.',
    ],
    [
        'norma' => 'Decision Andina 486 de 2000 / Ley 23 de 1982 / Ley 256 de 1996',
        'articulo' => 'Marcas, obras y competencia desleal',
        'texto' => 'Protegen signos distintivos, contenidos y reputacion comercial frente a confusion o aprovechamiento indebido.',
        'uso' => 'Aplica a uso de apellido, marcas, reputacion, logos e imagenes.',
    ],
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 23 y 24',
        'texto' => 'Regulan deberes y responsabilidad de administradores.',
        'uso' => 'Aplica a voceria empresarial, crisis, uso de recursos sociales, donaciones y patrocinios.',
    ],
    [
        'norma' => 'Estatuto Tributario / Decreto 2150 de 2017',
        'articulo' => 'Arts. 125-1, 125-5, 257 y 258; regimen tributario especial',
        'texto' => 'Regulan requisitos y efectos tributarios de donaciones y entidades sin animo de lucro.',
        'uso' => 'Base para donaciones, fundacion familiar, certificados, deducibilidad/descuentos y seguimiento.',
    ],
    [
        'norma' => 'Ley 99 de 1993',
        'articulo' => 'Sistema ambiental',
        'texto' => 'Desarrolla politica ambiental y principios de gestion ambiental en Colombia.',
        'uso' => 'Base para compromisos ambientales y sostenibilidad.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 18 a 24, 31 y 32',
        'texto' => 'Recomienda informacion, control, riesgos, protocolo y separacion de roles.',
        'uso' => 'Marco consultivo para reputacion, comunicacion, sostenibilidad y responsabilidad social.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$cat20Academy = [
    'DEC-191' => [
        'decision' => 'Definir mapa de riesgos criticos familiares, empresariales y patrimoniales, con responsables y controles.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre diligencia de administradores; Guia Colombiana Medidas 18 a 24 sobre control, riesgos e informacion; Ley 1258/2008 arts. 17 y 24 para formalizar politicas internas y acuerdos.',
        'ejemplo' => 'El mapa incluira riesgos de sucesion, dependencia de fundador, liquidez, litigios, ciberseguridad, reputacion, concentracion patrimonial, seguros, cumplimiento y conflicto familiar.',
        'claro' => 'Debe quedar claro: riesgo, causa, impacto, probabilidad, responsable, control, alerta, fecha de revision y evidencia.',
        'precedente' => 'Riesgo no gestionado termina convertido en urgencia; el protocolo debe anticipar y asignar duenos.',
    ],
    'DEC-192' => [
        'decision' => 'Definir plan ante ausencia, fallecimiento, incapacidad o retiro de fundador, gerente, tecnico indispensable o proveedor critico.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 22, 23 y 24 sobre administradores; Ley 1258/2008 arts. 17, 26 y 27 sobre administracion y responsabilidad; Ley 1996/2019 sobre apoyos para personas con discapacidad cuando aplique.',
        'ejemplo' => 'Cada rol critico tendra suplente, manual de funciones, poderes, accesos, agenda de decisiones, plan de empalme y contacto de asesores clave.',
        'claro' => 'Debe quedar claro: persona clave, suplente, facultades, accesos, documentos, plazo, limites, comunicacion y prueba de activacion.',
        'precedente' => 'La continuidad no puede depender de que una persona recuerde todo o tenga todas las claves.',
    ],
    'DEC-193' => [
        'decision' => 'Definir si cada empresa debe contar con plan documentado de continuidad empresarial y recuperacion operativa.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 23 sobre diligencia; Guia Colombiana Medidas 18 a 24 sobre control y riesgos; Ley 1581/2012 y Ley 1273/2009 si continuidad incluye datos y sistemas.',
        'ejemplo' => 'Cada empresa tendra plan de continuidad con escenarios de falla operativa, ciberataque, perdida de proveedor, ausencia de gerente, crisis reputacional y desastre fisico.',
        'claro' => 'Debe quedar claro: escenarios, responsables, tiempos maximos, respaldo, comunicacion, proveedores, pruebas y actualizacion.',
        'precedente' => 'Un plan no probado es una buena intencion; debe ensayarse y actualizarse.',
    ],
    'DEC-194' => [
        'decision' => 'Definir quien tendra facultades temporales para decidir durante una crisis y que limites tendra.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 arts. 17, 26 y 27; Ley 222/1995 arts. 22 a 24; Codigo de Comercio y estatutos sobre representante legal, junta y organos. Ley 222 arts. 19 a 21 para documentar decisiones urgentes.',
        'ejemplo' => 'Durante crisis, el comite de emergencia podra autorizar gastos, comunicaciones y medidas operativas dentro de limites, reportando a junta o accionistas en 72 horas.',
        'claro' => 'Debe quedar claro: crisis definida, comite, facultades, topes, plazo, reportes, decisiones prohibidas, ratificacion y cierre.',
        'precedente' => 'La emergencia exige velocidad, pero tambien limites para evitar abuso o decisiones irreversibles sin control.',
    ],
    'DEC-195' => [
        'decision' => 'Definir custodia de estatutos, contratos, testamentos, seguros, registros, claves, accesos, actas y documentos criticos.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 21 sobre actas y arts. 34 a 38 sobre soportes financieros; Ley 1581/2012 arts. 4, 9 y 17 sobre datos; Ley 1273/2009 sobre informacion y datos. Codigo de Comercio art. 19 sobre contabilidad cuando aplique.',
        'ejemplo' => 'Los documentos criticos se custodiaran en repositorio fisico y digital con inventario, responsables, accesos por rol, copias de seguridad y protocolo de emergencia.',
        'claro' => 'Debe quedar claro: documentos, custodio, ubicacion, acceso, respaldo, actualizacion, vencimientos, confidencialidad y auditoria.',
        'precedente' => 'Cuando faltan documentos, las decisiones se retrasan y la familia pierde poder de reaccion.',
    ],
    'DEC-196' => [
        'decision' => 'Definir responsable de convertir cada decision aprobada en documentos, contratos, reformas, politicas, calendarios y acciones concretas.',
        'fundamento' => 'Articulos guia: Ley 222/1995 arts. 19 a 21 sobre decisiones y actas; Ley 1258/2008 art. 24 si acuerdos deben formalizarse; Codigo Civil art. 1602 para documentos obligatorios.',
        'ejemplo' => 'Cada decision tendra responsable, entregable, documento soporte, fecha objetivo, asesor requerido, estado y evidencia de implementacion.',
        'claro' => 'Debe quedar claro: responsable, entregable, fecha, recursos, aprobador, soporte, verificador y consecuencia de atraso.',
        'precedente' => 'Una decision sin responsable se convierte en deseo; el protocolo vale por lo que se implementa.',
    ],
    'DEC-197' => [
        'decision' => 'Definir cronograma para ejecutar decisiones aprobadas, priorizando riesgos altos y documentos urgentes.',
        'fundamento' => 'Articulos guia: Ley 222/1995 art. 21 sobre actas y seguimiento; Guia Colombiana Medidas 31 y 32 sobre protocolo y roles; Ley 1258/2008 arts. 17 y 24 para armonizar acciones con estatutos/acuerdos.',
        'ejemplo' => 'Las decisiones se ejecutaran en fases: urgentes en 90 dias, estructurales en 6 meses y complementarias en 12 meses, con revision mensual.',
        'claro' => 'Debe quedar claro: fases, prioridades, fechas, dependencias, responsables, presupuesto, alertas y forma de reportar avance.',
        'precedente' => 'El cronograma evita que el protocolo sea aprobado solemnemente y archivado silenciosamente.',
    ],
    'DEC-198' => [
        'decision' => 'Definir indicadores para medir cumplimiento del protocolo, avance documental, reuniones, riesgos, formacion y revisiones.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medidas 18 a 24, 31 y 32 sobre informacion, control, protocolo y roles. Ley 222/1995 arts. 45 a 47 sobre rendicion e informes cuando aplique a administradores.',
        'ejemplo' => 'Se medira porcentaje de decisiones implementadas, documentos firmados, riesgos con control, reuniones cumplidas, vencimientos atendidos y formacion ejecutada.',
        'claro' => 'Debe quedar claro: indicador, formula, meta, responsable, frecuencia, fuente, semaforo y accion correctiva.',
        'precedente' => 'Lo que no se mide se vuelve opinion; el indicador convierte el protocolo en sistema de gestion.',
    ],
    'DEC-199' => [
        'decision' => 'Definir mayoria y procedimiento para modificar, adicionar, suspender o eliminar reglas del protocolo.',
        'fundamento' => 'Articulos guia: Ley 1258/2008 art. 24 sobre acuerdos de accionistas y vigencia; Ley 222/1995 arts. 19 a 21 sobre reuniones, decisiones y actas; Codigo Civil art. 1602 sobre fuerza obligatoria de acuerdos validos.',
        'ejemplo' => 'Las modificaciones requeriran propuesta escrita, periodo de revision, concepto juridico si afecta estatutos/acuerdos y aprobacion por mayoria calificada definida.',
        'claro' => 'Debe quedar claro: quien propone, plazo, mayoria, versionamiento, documentos afectados, vigencia, comunicacion y adhesiones.',
        'precedente' => 'Un protocolo inmodificable se vuelve rigido; uno modificable sin regla se vuelve inestable.',
    ],
    'DEC-200' => [
        'decision' => 'Definir cada cuanto se revisara integralmente el protocolo y quien coordinara su actualizacion.',
        'fundamento' => 'Articulos guia: Guia Colombiana Medida 31 sobre protocolo de familia; Ley 1258/2008 art. 24 si hay acuerdos de accionistas; Ley 222/1995 art. 21 sobre actas y trazabilidad.',
        'ejemplo' => 'El protocolo se revisara integralmente cada dos anos y extraordinariamente ante sucesion, venta relevante, ingreso de nueva generacion, conflicto grave o reforma estatutaria.',
        'claro' => 'Debe quedar claro: frecuencia, coordinador, metodologia, documentos, participantes, asesor externo, aprobacion y version vigente.',
        'precedente' => 'La familia cambia, la empresa cambia y la ley cambia; el protocolo debe respirar sin perder estabilidad.',
    ],
];
$cat20LegalReferences = [
    [
        'norma' => 'Ley 222 de 1995',
        'articulo' => 'Arts. 19 a 24, 34 a 38 y 45 a 47',
        'texto' => 'Regula decisiones, actas, administradores, deberes, responsabilidad, estados financieros y rendicion de cuentas.',
        'uso' => 'Base para riesgos, continuidad, emergencias, custodia documental, implementacion e indicadores.',
    ],
    [
        'norma' => 'Ley 1258 de 2008',
        'articulo' => 'Arts. 17, 24, 26 y 27',
        'texto' => 'Regula organizacion interna, acuerdos de accionistas, representacion legal y responsabilidad de administradores.',
        'uso' => 'Base para responsables, facultades de emergencia, modificaciones y revision del protocolo.',
    ],
    [
        'norma' => 'Ley 1581 de 2012 / Ley 1273 de 2009',
        'articulo' => 'Datos, informacion y seguridad',
        'texto' => 'Regulan tratamiento de datos personales y proteccion de informacion y sistemas.',
        'uso' => 'Aplica a custodia documental, claves, accesos, continuidad tecnologica y repositorios.',
    ],
    [
        'norma' => 'Ley 1996 de 2019',
        'articulo' => 'Capacidad legal y apoyos',
        'texto' => 'Regula apoyos para personas con discapacidad y respeto por voluntad/preferencias.',
        'uso' => 'Aplica a ausencia o incapacidad de personas clave cuando corresponda.',
    ],
    [
        'norma' => 'Codigo Civil',
        'articulo' => 'Art. 1602',
        'texto' => 'Los contratos validamente celebrados obligan a quienes los suscriben.',
        'uso' => 'Base para implementacion, modificaciones, versiones y compromisos del protocolo.',
    ],
    [
        'norma' => 'Codigo de Comercio',
        'articulo' => 'Art. 19 y reglas societarias/contables',
        'texto' => 'Incluye deberes de comerciantes, contabilidad y conservacion de soportes cuando aplique.',
        'uso' => 'Base para custodia documental y continuidad empresarial.',
    ],
    [
        'norma' => 'Guia Colombiana de Gobierno Corporativo',
        'articulo' => 'Medidas 18 a 24, 28 a 32',
        'texto' => 'Recomienda informacion, control, riesgos, organos de familia, protocolo y roles.',
        'uso' => 'Marco consultivo para mapa de riesgos, implementacion, indicadores y revision periodica.',
        'url' => 'https://www.icgc.com.co/wp-content/uploads/2018/01/Gui%CC%81a-colombiana-de-gobierno-corporativo-para-sociedades-cerradas-y-de-familia.pdf',
    ],
];
$guidedCategoryWorkbooks = [
    'CAT-01' => [
        'title' => 'Documento unico CAT-01',
        'intro' => 'Trabaja identidad, proposito y vision familiar en un solo instrumento. Cada componente se diligencia por separado para trazabilidad, pero todos pueden soportarse en una misma acta o documento CAT-01.',
        'badge' => '10 componentes integrados',
        'academy' => $cat01Academy,
        'legal' => $cat01LegalReferences,
    ],
    'CAT-02' => [
        'title' => 'Documento unico CAT-02',
        'intro' => 'Define el alcance del protocolo: que empresas y activos cobija, quienes integran la familia para estos efectos, como se adhieren nuevas generaciones y accionistas, y como se revisa la vigencia.',
        'badge' => '10 componentes integrados',
        'academy' => $cat02Academy,
        'legal' => $cat02LegalReferences,
    ],
    'CAT-03' => [
        'title' => 'Documento unico CAT-03',
        'intro' => 'Define como se conserva, distribuye y ejerce la propiedad familiar: control, porcentaje minimo, ramas, voto, clases de acciones, usufructo, propiedad sin empleo y posible holding.',
        'badge' => '10 componentes integrados',
        'academy' => $cat03Academy,
        'legal' => $cat03LegalReferences,
    ],
    'CAT-04' => [
        'title' => 'Documento unico CAT-04',
        'intro' => 'Define como entran, salen y se transfieren acciones: adquirentes autorizados, preferencia, ventas internas o a terceros, donaciones, garantias, retiros, recompras y venta de control.',
        'badge' => '10 componentes integrados',
        'academy' => $cat04Academy,
        'legal' => $cat04LegalReferences,
    ],
    'CAT-05' => [
        'title' => 'Documento unico CAT-05',
        'intro' => 'Define reglas patrimoniales y de participacion para matrimonios, uniones, parejas, separaciones y confidencialidad, protegiendo la continuidad accionaria sin desconocer derechos familiares.',
        'badge' => '10 componentes integrados',
        'academy' => $cat05Academy,
        'legal' => $cat05LegalReferences,
    ],
    'CAT-06' => [
        'title' => 'Documento unico CAT-06',
        'intro' => 'Define como se protege la continuidad ante fallecimiento, incapacidad o sucesion: testamentos, herencia de acciones, menores, voto transitorio, albacea, seguros y plan de emergencia.',
        'badge' => '10 componentes integrados',
        'academy' => $cat06Academy,
        'legal' => $cat06LegalReferences,
    ],
    'CAT-07' => [
        'title' => 'Documento unico CAT-07',
        'intro' => 'Define el gobierno de la familia: asamblea, consejo, representacion por ramas, eleccion, periodos, competencias, actas, presupuesto y recursos para que la familia decida con orden sin invadir organos societarios.',
        'badge' => '10 componentes integrados',
        'academy' => $cat07Academy,
        'legal' => $cat07LegalReferences,
    ],
    'CAT-08' => [
        'title' => 'Documento unico CAT-08',
        'intro' => 'Define el gobierno corporativo y la administracion empresarial: junta directiva, independientes, cupos familiares, perfiles, separacion presidencia-gerencia, comites, gerencia externa y rendicion de cuentas.',
        'badge' => '10 componentes integrados',
        'academy' => $cat08Academy,
        'legal' => $cat08LegalReferences,
    ],
    'CAT-09' => [
        'title' => 'Documento unico CAT-09',
        'intro' => 'Define la matriz de decisiones y asuntos reservados: mayorias, facultades de fundadores, vetos, endeudamiento, inversiones, activos esenciales, nuevas sociedades, garantias a vinculados, cambio de actividad y desbloqueo de empates.',
        'badge' => '10 componentes integrados',
        'academy' => $cat09Academy,
        'legal' => $cat09LegalReferences,
    ],
    'CAT-10' => [
        'title' => 'Documento unico CAT-10',
        'intro' => 'Define la politica de empleo familiar y desarrollo profesional: ingreso sin privilegio automatico, vacante real, requisitos, experiencia, seleccion, periodo de prueba, jerarquia, evaluacion, promocion y retiro laboral.',
        'badge' => '10 componentes integrados',
        'academy' => $cat10Academy,
        'legal' => $cat10LegalReferences,
    ],
    'CAT-11' => [
        'title' => 'Documento unico CAT-11',
        'intro' => 'Define la relacion economica familia-empresa: salarios de mercado, separacion salario/dividendo, honorarios, beneficios, gastos prohibidos, prestamos, garantias, ayudas extraordinarias y operaciones con vinculados.',
        'badge' => '10 componentes integrados',
        'academy' => $cat11Academy,
        'legal' => $cat11LegalReferences,
    ],
    'CAT-12' => [
        'title' => 'Documento unico CAT-12',
        'intro' => 'Define la politica financiera de propietarios: dividendos, reinversion, reservas, suspension de dividendos, necesidades familia-empresa, aportes, falta de aporte, dilucion, inversionistas externos y prestamos familiares a la empresa.',
        'badge' => '10 componentes integrados',
        'academy' => $cat12Academy,
        'legal' => $cat12LegalReferences,
    ],
    'CAT-13' => [
        'title' => 'Documento unico CAT-13',
        'intro' => 'Define la politica de valoracion y pago: frecuencia, metodos, valorador independiente, fecha de corte, intangibles, prima de control, descuentos, diferencias de valoracion, forma de pago y financiacion de compras.',
        'badge' => '10 componentes integrados',
        'academy' => $cat13Academy,
        'legal' => $cat13LegalReferences,
    ],
    'CAT-14' => [
        'title' => 'Documento unico CAT-14',
        'intro' => 'Define la proteccion patrimonial: inventario, separacion de bienes, inmuebles familiares, uso de activos, holding patrimonial, diversificacion, inversiones, garantias, seguros y venta de activos relevantes.',
        'badge' => '10 componentes integrados',
        'academy' => $cat14Academy,
        'legal' => $cat14LegalReferences,
    ],
    'CAT-15' => [
        'title' => 'Documento unico CAT-15',
        'intro' => 'Define la propiedad intelectual y los activos tecnologicos: inventario, titularidad, cesion de derechos, codigo fuente, repositorios, dominios, registros, licencias de terceros, continuidad y explotacion comercial.',
        'badge' => '10 componentes integrados',
        'academy' => $cat15Academy,
        'legal' => $cat15LegalReferences,
    ],
    'CAT-16' => [
        'title' => 'Documento unico CAT-16',
        'intro' => 'Define la politica de nuevos negocios y emprendimientos familiares: oportunidades, emprendimientos propios, competencia, uso de marca, financiacion, evaluacion, modalidad de inversion, limite de riesgo, fracaso e integracion al grupo.',
        'badge' => '10 componentes integrados',
        'academy' => $cat16Academy,
        'legal' => $cat16LegalReferences,
    ],
    'CAT-17' => [
        'title' => 'Documento unico CAT-17',
        'intro' => 'Define conducta, etica, confidencialidad y solucion de conflictos: codigo de conducta, conflictos de interes, abstencion, negociacion, mediacion, arbitraje, sanciones, informacion reservada, canal de denuncias y convivencia.',
        'badge' => '10 componentes integrados',
        'academy' => $cat17Academy,
        'legal' => $cat17LegalReferences,
    ],
    'CAT-18' => [
        'title' => 'Documento unico CAT-18',
        'intro' => 'Define educacion, formacion y nuevas generaciones: formacion patrimonial, historia familiar, edad de incorporacion, programa permanente, practicas, mentoria, liderazgo, formacion como accionistas, participacion gradual y financiacion educativa.',
        'badge' => '10 componentes integrados',
        'academy' => $cat18Academy,
        'legal' => $cat18LegalReferences,
    ],
    'CAT-19' => [
        'title' => 'Documento unico CAT-19',
        'intro' => 'Define reputacion, comunicacion y responsabilidad social: voceria, redes, informacion publica, crisis reputacional, uso de nombre y marca, responsabilidad social, donaciones, fundacion, patrocinios y sostenibilidad.',
        'badge' => '10 componentes integrados',
        'academy' => $cat19Academy,
        'legal' => $cat19LegalReferences,
    ],
    'CAT-20' => [
        'title' => 'Documento unico CAT-20',
        'intro' => 'Define continuidad, riesgos, implementacion y actualizacion: mapa de riesgos, personas clave, continuidad empresarial, gobierno en emergencias, custodia documental, responsables, cronograma, indicadores, modificaciones y revision periodica.',
        'badge' => '10 componentes integrados',
        'academy' => $cat20Academy,
        'legal' => $cat20LegalReferences,
    ],
];
?>
<div class="heading compact workspace-heading decision-heading">
    <div>
        <small>06. MATRIZ DE DECISIONES DEL PROTOCOLO</small>
        <h1>Decisiones</h1>
        <p>Objetivo: analizar, aprobar e implementar decisiones familiares. Las alertas son candidatas a revision humana; no crean riesgos definitivos.</p>
    </div>
</div>

<details class="decision-academy" open>
    <summary>
        <span>
            <strong>Academia rapida para diligenciar decisiones</strong>
            <small>Lee esto antes de empezar. La idea es avanzar con calma, una decision a la vez.</small>
        </span>
    </summary>
    <div class="decision-academy-body">
        <article>
            <strong>1. Empieza por una categoria</strong>
            <p>Abre solo una categoria. Trabaja primero sus 10 decisiones internas antes de pasar a la siguiente.</p>
        </article>
        <article>
            <strong>2. Lee la pregunta y define si aplica</strong>
            <p>Usa aplicabilidad para decir si la decision se trabaja ahora, mas adelante o no aplica. Si no aplica, escribe la justificacion.</p>
        </article>
        <article>
            <strong>3. Cambia el estado de la decision</strong>
            <p>Usa pendiente, en analisis, aplazada, aprobada o rechazada segun el avance real de la familia.</p>
        </article>
        <article>
            <strong>4. Escribe la regla aprobada</strong>
            <p>Cuando una decision quede aprobada, escribe la regla clara y agrega la fecha de aprobacion.</p>
        </article>
        <article>
            <strong>5. Revisa documentos</strong>
            <p>Los documentos potenciales vienen del catalogo. Los existentes se controlan en 05_Documentos. Los pendientes son soportes que faltan.</p>
        </article>
        <article>
            <strong>6. Implementa y verifica</strong>
            <p>Implementada significa ejecutada. Verificada significa comprobada con evidencia documental concreta.</p>
        </article>
        <article>
            <strong>7. Atiende alertas</strong>
            <p>Las alertas indican faltantes, vencimientos o posibles riesgos. Enviar a revision de riesgos solo prepara una propuesta; no crea un riesgo definitivo.</p>
        </article>
        <article>
            <strong>Ruta recomendada</strong>
            <p>Aplicabilidad -> Estado decision -> Responsable -> Fecha objetivo -> Documentos -> Regla aprobada -> Implementacion -> Verificacion.</p>
        </article>
        <article>
            <strong>Base juridica transversal</strong>
            <p>Consulta rapida: <a href="<?= $e($legalReferenceBaseUrls['ley 222']) ?>" target="_blank" rel="noopener noreferrer">Ley 222/1995</a>, <a href="<?= $e($legalReferenceBaseUrls['ley 1258']) ?>" target="_blank" rel="noopener noreferrer">Ley 1258/2008</a>, <a href="<?= $e($legalReferenceBaseUrls['codigo de comercio']) ?>" target="_blank" rel="noopener noreferrer">Codigo de Comercio</a>, <a href="<?= $e($legalReferenceBaseUrls['codigo civil']) ?>" target="_blank" rel="noopener noreferrer">Codigo Civil</a> y <a href="<?= $e($legalReferenceBaseUrls['guia colombiana']) ?>" target="_blank" rel="noopener noreferrer">Guia Colombiana de Gobierno Corporativo</a>.</p>
        </article>
        <article>
            <strong>Jurisprudencia y doctrina base</strong>
            <p>Revisa <a href="<?= $e($legalReferenceBaseUrls['c-014 de 2010']) ?>" target="_blank" rel="noopener noreferrer">C-014/2010</a> para flexibilidad S.A.S. y conflictos societarios, <a href="<?= $e($legalReferenceBaseUrls['c-305 de 2013']) ?>" target="_blank" rel="noopener noreferrer">C-305/2013</a> para arbitraje, y la <a href="<?= $e($legalReferenceBaseUrls['guia supersociedades']) ?>" target="_blank" rel="noopener noreferrer">Guia Supersociedades de conflictos de interes</a>.</p>
        </article>
    </div>
</details>

<section class="decision-summary" aria-label="Resumen de decisiones">
    <article><strong><?= $e(count($decisionGroups)) ?></strong><span>Categorias</span></article>
    <article><strong data-decision-answered><?= $e($activeDecisionGroup['respondidas'] ?? 0) ?></strong><span>Con seguimiento</span></article>
    <article class="is-ok"><strong data-decision-approved><?= $e($activeDecisionGroup['aprobadas'] ?? 0) ?></strong><span>Aprobadas</span></article>
    <article class="is-ok"><strong data-decision-implemented><?= $e($activeDecisionGroup['implementadas'] ?? 0) ?></strong><span>Implementadas</span></article>
    <article class="is-ok"><strong data-decision-verified><?= $e($activeDecisionGroup['verificadas'] ?? 0) ?></strong><span>Verificadas</span></article>
    <article class="is-warning"><strong data-decision-pending><?= $e($activeDecisionGroup['pendientes'] ?? 0) ?></strong><span>Pendientes</span></article>
    <article class="is-danger"><strong data-decision-expired><?= $e($activeDecisionGroup['vencidas'] ?? 0) ?></strong><span>Vencidas</span></article>
    <article class="is-info"><strong data-decision-progress><?= $e($activeDecisionProgress) ?>%</strong><span>Avance verificado</span></article>
</section>

<section class="card decision-records">
    <div class="decision-toolbar">
        <div>
            <h2>Catalogo operativo</h2>
            <p class="muted"><span data-decision-visible><?= $e($activeDecisionGroup['total'] ?? 0) ?></span> decisiones visibles de <span data-decision-active-total><?= $e($activeDecisionGroup['total'] ?? 0) ?></span></p>
        </div>
        <div class="decision-tools">
            <label class="decision-search">Buscar<input type="search" data-decision-search placeholder="DEC, tema, pregunta, respuesta o responsable"></label>
            <select data-decision-filter aria-label="Filtrar por estado">
                <option value="todos">Todos los estados</option>
                <option value="Pendiente de analizar">Pendientes</option>
                <option value="Aprobada">Aprobadas</option>
                <option value="Implementada">Implementadas</option>
                <option value="Verificada">Verificadas</option>
                <option value="Vencida">Vencidas</option>
                <option value="Con documento obligatorio pendiente">Documentos pendientes</option>
                <option value="Con documento provisional">Documentos provisionales</option>
                <option value="Posible riesgo pendiente de revisión humana">Alertas de riesgo</option>
            </select>
        </div>
    </div>

    <nav class="decision-category-tabs" aria-label="Categorias de decisiones" data-decision-tabs>
        <?php foreach ($decisionGroups as $group): ?>
            <?php
            $progress = (int) $group['total'] > 0 ? (int) round(((int) $group['verificadas'] / (int) $group['total']) * 100) : 0;
            ?>
            <button
                type="button"
                class="<?= $group['codigo'] === 'CAT-01' ? 'is-active' : '' ?>"
                data-decision-tab="<?= $e($group['codigo']) ?>"
                data-total="<?= $e($group['total']) ?>"
                data-answered="<?= $e($group['respondidas']) ?>"
                data-approved="<?= $e($group['aprobadas']) ?>"
                data-implemented="<?= $e($group['implementadas']) ?>"
                data-verified="<?= $e($group['verificadas']) ?>"
                data-pending="<?= $e($group['pendientes']) ?>"
                data-expired="<?= $e($group['vencidas']) ?>"
                data-progress="<?= $e($progress) ?>"
            >
                <strong><?= $e($group['codigo']) ?></strong>
                <span><?= $e($group['nombre']) ?></span>
            </button>
        <?php endforeach; ?>
    </nav>

    <div class="decision-categories" data-decision-groups>
        <?php foreach ($decisionGroups as $group): ?>
            <details class="decision-category" data-decision-category="<?= $e($group['codigo']) ?>" <?= $group['codigo'] === 'CAT-01' ? 'open' : '' ?> <?= $group['codigo'] !== 'CAT-01' ? 'hidden' : '' ?>>
                <summary>
                    <span class="decision-category-title">
                        <strong><?= $e($group['codigo']) ?> <?= $e($group['nombre']) ?></strong>
                        <small><?= $e($group['total']) ?> decisiones</small>
                    </span>
                    <span class="decision-category-stats">
                        <span><strong><?= $e($group['total']) ?></strong> total</span>
                        <span class="is-warning"><strong><?= $e($group['pendientes']) ?></strong> pendientes</span>
                        <span class="is-ok"><strong><?= $e($group['aprobadas']) ?></strong> aprobadas</span>
                        <span class="is-ok"><strong><?= $e($group['implementadas']) ?></strong> implementadas</span>
                        <span class="is-ok"><strong><?= $e($group['verificadas']) ?></strong> verificadas</span>
                        <span class="is-danger"><strong><?= $e($group['vencidas']) ?></strong> vencidas</span>
                        <span class="is-warning"><strong><?= $e($group['documentos_pendientes']) ?></strong> docs pendientes</span>
                        <span><strong><?= $e($group['provisionales']) ?></strong> provisionales</span>
                        <span class="is-danger"><strong><?= $e($group['alertas_riesgo']) ?></strong> alertas riesgo</span>
                    </span>
                </summary>
                <div class="decision-list">
                    <?php $workbook = $guidedCategoryWorkbooks[(string) $group['codigo']] ?? null; ?>
                    <?php if ($workbook): ?>
                        <section class="cat01-workbook">
                            <div class="cat01-workbook-head">
                                <div>
                                    <h2><?= $e($workbook['title']) ?></h2>
                                    <p><?= $e($workbook['intro']) ?></p>
                                </div>
                                <span><?= $e($workbook['badge']) ?></span>
                            </div>

                            <details class="cat01-legal-compendium" open>
                                <summary>
                                    <span>
                                        <strong>Fundamento juridico y consultivo</strong>
                                        <small>Articulos y criterios utiles para el analista. Texto operativo, no reemplaza revision juridica del caso concreto.</small>
                                    </span>
                                </summary>
                                <div class="cat01-legal-grid">
                                    <?php foreach ($workbook['legal'] as $reference): ?>
                                        <?php $referenceUrl = $legalReferenceUrl(is_array($reference) ? $reference : []); ?>
                                        <article>
                                            <span><?= $e($reference['norma']) ?></span>
                                            <h3><?= $e($reference['articulo']) ?></h3>
                                            <p><?= $e($reference['texto']) ?></p>
                                            <small><?= $e($reference['uso']) ?></small>
                                            <?php if ($referenceUrl !== ''): ?>
                                                <a class="cat01-reference-link" href="<?= $e($referenceUrl) ?>" target="_blank" rel="noopener noreferrer">Abrir fuente completa</a>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </details>

                            <div class="cat01-decision-table">
                                <div class="cat01-table-head">
                                    <span>Componente</span>
                                    <span>Decision a concretar</span>
                                    <span>Regla o acuerdo</span>
                                    <span>Seguimiento</span>
                                </div>
                                <?php foreach ($group['rows'] as $catRow): ?>
                                    <?php
                                    $guide = $workbook['academy'][(string) $catRow['codigo']] ?? ['decision' => '', 'fundamento' => '', 'ejemplo' => '', 'claro' => '', 'precedente' => ''];
                                    $catAlerts = is_array($catRow['alertas_calculadas'] ?? null) ? $catRow['alertas_calculadas'] : [];
                                    $catSearch = strtolower(trim(implode(' ', [$catRow['codigo'], $catRow['categoria_nombre'], $catRow['tema'], $catRow['pregunta'], $catRow['respuesta'], $catRow['responsable'], $catRow['estado_decision'], $catRow['estado_implementacion'], implode(' ', $catAlerts)])));
                                    ?>
                                    <article id="<?= $e($catRow['codigo']) ?>" class="cat01-decision-row" data-decision-card data-code="<?= $e($catRow['codigo']) ?>" data-state="<?= $e($catRow['estado_decision']) ?>" data-implementation="<?= $e($catRow['estado_implementacion']) ?>" data-alerts="<?= $e(implode('|', $catAlerts)) ?>" data-search="<?= $e($catSearch) ?>">
                                        <div class="cat01-row-topic">
                                            <code><a href="#<?= $e($catRow['codigo']) ?>"><?= $e($catRow['codigo']) ?></a></code>
                                            <strong><?= $e($catRow['tema']) ?></strong>
                                            <small><?= $e($catRow['pregunta']) ?></small>
                                            <details>
                                                <summary>Ver guia</summary>
                                                <p><b>Fundamento:</b> <?= $e($guide['fundamento']) ?></p>
                                                <p><b>Debe quedar claro:</b> <?= $e($guide['claro']) ?></p>
                                                <p><b>Jurisprudencia/doctrina:</b> <?= $e($guide['precedente']) ?></p>
                                            </details>
                                        </div>
                                        <div class="cat01-row-decision">
                                            <p><?= $e($guide['decision']) ?></p>
                                        </div>
                                        <form class="decision-form is-guided-form cat01-inline-form" method="post" action="<?= $e($basePath) ?>/protocolo-familiar/decisiones/<?= $e($catRow['codigo']) ?>" data-decision-form>
                                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                            <label>Regla propuesta<textarea name="respuesta" rows="4" placeholder="<?= $e($guide['ejemplo']) ?>"><?= $e($catRow['respuesta']) ?></textarea></label>
                                            <label>Observaciones<textarea name="observaciones" rows="2" placeholder="Pendientes, dudas, alcance, soporte comun o temas que deben llevarse al acta de la categoria."><?= $e($catRow['observaciones']) ?></textarea></label>
                                            <div class="cat01-inline-controls">
                                                <label>Aplica<select name="aplica" required><?php foreach ($decisionOptions['aplicabilidad'] as $value) { $option($value, $catRow['aplica']); } ?></select></label>
                                                <label>Estado<select name="estado_decision" required><?php foreach ($decisionOptions['estado_decision'] as $value) { $option($value, $catRow['estado_decision']); } ?></select></label>
                                                <label>Prioridad<select name="prioridad_familiar" required><?php foreach ($decisionOptions['prioridad_familiar'] as $value) { $option($value, $catRow['prioridad_familiar']); } ?></select></label>
                                                <label>Responsable<input name="responsable" value="<?= $e($catRow['responsable']) ?>"></label>
                                                <label>Fecha objetivo<input name="fecha_objetivo" type="date" value="<?= $e($date($catRow['fecha_objetivo'])) ?>"></label>
                                                <label>Revision<input name="fecha_proxima_revision" type="date" value="<?= $e($date($catRow['fecha_proxima_revision'])) ?>"></label>
                                            </div>
                                            <details class="cat01-row-extra">
                                                <summary>Soporte de estado</summary>
                                                <div>
                                                    <label>Implementacion<select name="estado_implementacion" required><?php foreach ($decisionOptions['estado_implementacion'] as $value) { $option($value, $catRow['estado_implementacion']); } ?></select></label>
                                                    <label>Fecha aprobacion<input name="fecha_aprobacion" type="date" value="<?= $e($date($catRow['fecha_aprobacion'])) ?>"></label>
                                                    <label>Fecha real<input name="fecha_real_implementacion" type="date" value="<?= $e($date($catRow['fecha_real_implementacion'])) ?>"></label>
                                                    <label>Verificado por<input name="verificado_por" value="<?= $e($catRow['verificado_por']) ?>"></label>
                                                    <label>Fecha verificacion<input name="fecha_verificacion" type="date" value="<?= $e($date($catRow['fecha_verificacion'])) ?>"></label>
                                                </div>
                                                <label data-decision-conditional="aplicabilidad">Justificacion de aplicabilidad<textarea name="justificacion_aplicabilidad" rows="2"><?= $e($catRow['justificacion_aplicabilidad']) ?></textarea></label>
                                                <label data-decision-conditional="bloqueo">Motivo de bloqueo<textarea name="motivo_bloqueo" rows="2"><?= $e($catRow['motivo_bloqueo']) ?></textarea></label>
                                                <label data-decision-conditional="aplazamiento">Motivo de aplazamiento, rechazo o cancelacion<textarea name="motivo_aplazamiento_rechazo" rows="2"><?= $e($catRow['motivo_aplazamiento_rechazo']) ?></textarea></label>
                                                <label data-decision-conditional="sin_fecha">Justificacion sin fecha objetivo<textarea name="justificacion_sin_fecha_objetivo" rows="2"><?= $e($catRow['justificacion_sin_fecha_objetivo']) ?></textarea></label>
                                                <label data-decision-conditional="verificacion">Observacion de verificacion<textarea name="observacion_verificacion" rows="2"><?= $e($catRow['observacion_verificacion']) ?></textarea></label>
                                                <label data-decision-conditional="cambio">Motivo del cambio<textarea name="motivo_cambio" rows="2"><?= $e($catRow['motivo_cambio']) ?></textarea></label>
                                            </details>
                                            <div class="decision-actions">
                                                <span class="muted" data-decision-status></span>
                                                <button class="primary" type="submit">Guardar</button>
                                            </div>
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php else: ?>
                    <?php foreach ($group['rows'] as $row): ?>
                        <?php
                        $docs = is_array($row['documentos'] ?? null) ? $row['documentos'] : ['potenciales' => [], 'existentes' => [], 'pendientes' => [], 'resumen' => []];
                        $alerts = is_array($row['alertas_calculadas'] ?? null) ? $row['alertas_calculadas'] : [];
                        $history = is_array($row['historial'] ?? null) ? $row['historial'] : [];
                        $riskRequests = is_array($row['revision_riesgos'] ?? null) ? $row['revision_riesgos'] : [];
                        $searchText = strtolower(trim(implode(' ', [$row['codigo'], $row['categoria_nombre'], $row['tema'], $row['pregunta'], $row['respuesta'], $row['responsable'], $row['estado_decision'], $row['estado_implementacion'], implode(' ', $alerts)])));
                        $isGuidedCat01 = (string) $row['categoria_codigo'] === 'CAT-01';
                        $guide = $cat01Academy[(string) $row['codigo']] ?? null;
                        ?>
                        <details id="<?= $e($row['codigo']) ?>" class="decision-item<?= $isGuidedCat01 ? ' is-guided-cat01' : '' ?>" data-decision-card data-code="<?= $e($row['codigo']) ?>" data-state="<?= $e($row['estado_decision']) ?>" data-implementation="<?= $e($row['estado_implementacion']) ?>" data-alerts="<?= $e(implode('|', $alerts)) ?>" data-search="<?= $e($searchText) ?>">
                            <summary>
                                <span class="decision-question">
                                    <code><a href="#<?= $e($row['codigo']) ?>"><?= $e($row['codigo']) ?></a></code>
                                    <strong><?= $e($row['tema']) ?></strong>
                                    <small><?= $e($row['pregunta']) ?></small>
                                </span>
                                <span class="decision-badges">
                                    <span><?= $e($row['aplica']) ?></span>
                                    <span class="decision-state state-<?= $e($stateClass($row['estado_decision'])) ?>" data-decision-state-label><?= $e($row['estado_decision']) ?></span>
                                    <span class="decision-state state-<?= $e($stateClass($row['estado_implementacion'])) ?>" data-decision-implementation-label><?= $e($row['estado_implementacion']) ?></span>
                                    <span>Cat: <?= $e($row['prioridad_sugerida']) ?></span>
                                    <span>Fam: <?= $e($row['prioridad_familiar']) ?></span>
                                    <span><?= $e($row['responsable'] ?: 'Sin responsable') ?></span>
                                    <span><?= $e($date($row['fecha_objetivo']) ?: 'Sin fecha') ?></span>
                                    <?php if ($alerts !== []): ?><span class="is-danger"><?= count($alerts) ?> alertas</span><?php endif; ?>
                                </span>
                            </summary>
                            <div class="decision-detail">
                                <?php if ($isGuidedCat01): ?>
                                    <section class="decision-guided-intro">
                                        <div>
                                            <strong>Academia CAT-01: decidir con fundamento</strong>
                                            <p>Lee primero el sentido juridico y el ejemplo. Luego deja una regla corta, revisable y facil de explicar a la familia.</p>
                                        </div>
                                        <ol>
                                            <li><span>1</span> Entender</li>
                                            <li><span>2</span> Decidir</li>
                                            <li><span>3</span> Soportar</li>
                                            <li><span>4</span> Verificar</li>
                                        </ol>
                                    </section>
                                <?php endif; ?>
                                <?php if ($isGuidedCat01 && $guide): ?>
                                    <div class="decision-cat01-academy">
                                        <article><h3>Que se decide</h3><p><?= $e($guide['decision']) ?></p></article>
                                        <article><h3>Fundamento juridico</h3><p><?= $e($guide['fundamento']) ?></p></article>
                                        <article><h3>Ejemplo orientador</h3><p><?= $e($guide['ejemplo']) ?></p></article>
                                        <article><h3>Debe quedar claro</h3><p><?= $e($guide['claro']) ?></p></article>
                                        <article><h3>Jurisprudencia / doctrina</h3><p><?= $e($guide['precedente']) ?></p></article>
                                    </div>
                                <?php else: ?>
                                    <div class="decision-context">
                                        <article><h3>Objetivo</h3><p><?= $e($row['objetivo']) ?></p></article>
                                        <article><h3>Opciones sugeridas</h3><p><?= $e($row['opciones_sugeridas']) ?></p></article>
                                        <article class="decision-alert"><h3>Alerta sugerida de riesgo</h3><p><?= $e($row['riesgo_sugerido']) ?></p><small>No crea registros definitivos en 07_Riesgos.</small></article>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$isGuidedCat01): ?>
                                <div class="decision-alert-list">
                                    <h3>Alertas calculadas</h3>
                                    <?php if ($alerts === []): ?><p class="muted">Sin alertas calculadas.</p><?php else: ?>
                                        <div><?php foreach ($alerts as $alert): ?><span class="<?= $alert === 'Posible riesgo pendiente de revisión humana' ? 'is-danger' : '' ?>"><?= $e($alert) ?></span><?php endforeach; ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <details class="decision-documents" <?= $isGuidedCat01 ? '' : 'open' ?>>
                                    <summary class="decision-documents-summary">
                                        <h3>Documentos</h3>
                                        <span class="muted">Req <?= $e($docs['resumen']['requeridos'] ?? 0) ?> · Exist <?= $e($docs['resumen']['existentes'] ?? 0) ?> · Pend <?= $e($docs['resumen']['pendientes'] ?? 0) ?> · Prov <?= $e($docs['resumen']['provisionales'] ?? 0) ?> · Evid <?= $e($docs['resumen']['evidencias_validas'] ?? 0) ?></span>
                                    </summary>
                                    <div class="decision-document-columns">
                                        <div>
                                            <h4>A. Potencialmente requeridos</h4>
                                            <?php foreach ($docs['potenciales'] as $doc): ?>
                                                <article class="decision-document <?= str_starts_with((string) $doc['documento_codigo'], 'PROP-DOC-') ? 'is-proposed' : '' ?>">
                                                    <code><?= $e($doc['documento_codigo']) ?></code>
                                                    <strong><?= $e($doc['documento_nombre']) ?></strong>
                                                    <small><?= $e($doc['tipo_relacion']) ?> · <?= $e($doc['exigibilidad']) ?> · Evidencia <?= $e($doc['evidencia_implementacion']) ?></small>
                                                    <?php if (str_starts_with((string) $doc['documento_codigo'], 'PROP-DOC-')): ?><span>Documento provisional - Pendiente de aprobacion juridica · <?= $e($doc['estado_provisional']) ?></span><?php endif; ?>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                        <div>
                                            <h4>B. Existentes en 05_Documentos</h4>
                                            <?php if (($docs['existentes'] ?? []) === []): ?><p class="muted">Sin documentos reales relacionados.</p><?php endif; ?>
                                            <?php foreach ($docs['existentes'] as $doc): ?>
                                                <article class="decision-document">
                                                    <code><?= $e($doc['codigo'] ?? '') ?></code>
                                                    <strong><?= $e($doc['documento_tipo'] ?? '') ?></strong>
                                                    <small><?= $e($doc['estado'] ?? '') ?> · <?= $e($doc['sujeto_nombre'] ?? '') ?></small>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                        <div>
                                            <h4>C. Pendientes</h4>
                                            <?php if (($docs['pendientes'] ?? []) === []): ?><p class="muted">Sin pendientes exigibles.</p><?php endif; ?>
                                            <?php foreach ($docs['pendientes'] as $doc): ?>
                                                <article class="decision-document is-pending">
                                                    <code><?= $e($doc['documento_codigo']) ?></code>
                                                    <strong><?= $e($doc['documento_nombre']) ?></strong>
                                                    <small><?= $e($doc['exigibilidad']) ?> · <?= $e($doc['momento_requerido']) ?></small>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </details>

                                <form class="decision-form<?= $isGuidedCat01 ? ' is-guided-form' : '' ?>" method="post" action="<?= $e($basePath) ?>/protocolo-familiar/decisiones/<?= $e($row['codigo']) ?>" data-decision-form>
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                    <?php if ($isGuidedCat01): ?>
                                        <div class="decision-form-guide">
                                            <strong>Captura practica</strong>
                                            <p>No necesitas completar todo hoy. Deja aplicabilidad, estado, responsable, fecha de trabajo y una primera regla propuesta.</p>
                                        </div>
                                        <div class="select-grid decision-grid decision-cat01-grid">
                                            <label>Aplicabilidad<select name="aplica" required><?php foreach ($decisionOptions['aplicabilidad'] as $value) { $option($value, $row['aplica']); } ?></select></label>
                                            <label>Estado decision<select name="estado_decision" required><?php foreach ($decisionOptions['estado_decision'] as $value) { $option($value, $row['estado_decision']); } ?></select></label>
                                            <label>Prioridad familiar<select name="prioridad_familiar" required><?php foreach ($decisionOptions['prioridad_familiar'] as $value) { $option($value, $row['prioridad_familiar']); } ?></select></label>
                                            <label>Responsable<input name="responsable" value="<?= $e($row['responsable']) ?>"></label>
                                            <label>Fecha objetivo<input name="fecha_objetivo" type="date" value="<?= $e($date($row['fecha_objetivo'])) ?>"></label>
                                            <label>Proxima revision<input name="fecha_proxima_revision" type="date" value="<?= $e($date($row['fecha_proxima_revision'])) ?>"></label>
                                        </div>
                                        <label class="decision-cat01-rule">Regla concreta o propuesta de decision<textarea name="respuesta" rows="4" placeholder="Ejemplo: La familia define como proposito..."><?= $e($row['respuesta']) ?></textarea></label>
                                        <label>Observaciones generales<textarea name="observaciones" rows="2" placeholder="Dudas, pendientes, alcance o comentarios para revisar en familia."><?= $e($row['observaciones']) ?></textarea></label>
                                        <details class="decision-cat01-advanced">
                                            <summary>Seguimiento, soporte y excepciones</summary>
                                            <div class="select-grid decision-grid">
                                                <label>Estado implementacion<select name="estado_implementacion" required><?php foreach ($decisionOptions['estado_implementacion'] as $value) { $option($value, $row['estado_implementacion']); } ?></select></label>
                                                <label>Fecha aprobacion<input name="fecha_aprobacion" type="date" value="<?= $e($date($row['fecha_aprobacion'])) ?>"></label>
                                                <label>Fecha real implementacion<input name="fecha_real_implementacion" type="date" value="<?= $e($date($row['fecha_real_implementacion'])) ?>"></label>
                                                <label>Verificado por<input name="verificado_por" value="<?= $e($row['verificado_por']) ?>"></label>
                                                <label>Fecha verificacion<input name="fecha_verificacion" type="date" value="<?= $e($date($row['fecha_verificacion'])) ?>"></label>
                                            </div>
                                            <label data-decision-conditional="aplicabilidad">Justificacion de aplicabilidad<textarea name="justificacion_aplicabilidad" rows="2"><?= $e($row['justificacion_aplicabilidad']) ?></textarea></label>
                                            <label data-decision-conditional="bloqueo">Motivo de bloqueo<textarea name="motivo_bloqueo" rows="2"><?= $e($row['motivo_bloqueo']) ?></textarea></label>
                                            <label data-decision-conditional="aplazamiento">Motivo de aplazamiento, rechazo o cancelacion<textarea name="motivo_aplazamiento_rechazo" rows="2"><?= $e($row['motivo_aplazamiento_rechazo']) ?></textarea></label>
                                            <label data-decision-conditional="sin_fecha">Justificacion sin fecha objetivo<textarea name="justificacion_sin_fecha_objetivo" rows="2"><?= $e($row['justificacion_sin_fecha_objetivo']) ?></textarea></label>
                                            <label data-decision-conditional="verificacion">Observacion de verificacion<textarea name="observacion_verificacion" rows="2"><?= $e($row['observacion_verificacion']) ?></textarea></label>
                                            <label data-decision-conditional="cambio">Motivo del cambio<textarea name="motivo_cambio" rows="2"><?= $e($row['motivo_cambio']) ?></textarea></label>
                                        </details>
                                    <?php else: ?>
                                        <div class="select-grid decision-grid">
                                            <label>Aplicabilidad<select name="aplica" required><?php foreach ($decisionOptions['aplicabilidad'] as $value) { $option($value, $row['aplica']); } ?></select></label>
                                            <label>Estado decision<select name="estado_decision" required><?php foreach ($decisionOptions['estado_decision'] as $value) { $option($value, $row['estado_decision']); } ?></select></label>
                                            <label>Estado implementacion<select name="estado_implementacion" required><?php foreach ($decisionOptions['estado_implementacion'] as $value) { $option($value, $row['estado_implementacion']); } ?></select></label>
                                            <label>Prioridad familiar<select name="prioridad_familiar" required><?php foreach ($decisionOptions['prioridad_familiar'] as $value) { $option($value, $row['prioridad_familiar']); } ?></select></label>
                                            <label>Responsable<input name="responsable" value="<?= $e($row['responsable']) ?>"></label>
                                            <label>Fecha objetivo<input name="fecha_objetivo" type="date" value="<?= $e($date($row['fecha_objetivo'])) ?>"></label>
                                            <label>Fecha aprobacion<input name="fecha_aprobacion" type="date" value="<?= $e($date($row['fecha_aprobacion'])) ?>"></label>
                                            <label>Proxima revision<input name="fecha_proxima_revision" type="date" value="<?= $e($date($row['fecha_proxima_revision'])) ?>"></label>
                                            <label>Fecha real implementacion<input name="fecha_real_implementacion" type="date" value="<?= $e($date($row['fecha_real_implementacion'])) ?>"></label>
                                            <label>Verificado por<input name="verificado_por" value="<?= $e($row['verificado_por']) ?>"></label>
                                            <label>Fecha verificacion<input name="fecha_verificacion" type="date" value="<?= $e($date($row['fecha_verificacion'])) ?>"></label>
                                        </div>
                                        <label>Respuesta o regla aprobada<textarea name="respuesta" rows="3"><?= $e($row['respuesta']) ?></textarea></label>
                                        <label data-decision-conditional="aplicabilidad">Justificacion de aplicabilidad<textarea name="justificacion_aplicabilidad" rows="2"><?= $e($row['justificacion_aplicabilidad']) ?></textarea></label>
                                        <label data-decision-conditional="bloqueo">Motivo de bloqueo<textarea name="motivo_bloqueo" rows="2"><?= $e($row['motivo_bloqueo']) ?></textarea></label>
                                        <label data-decision-conditional="aplazamiento">Motivo de aplazamiento, rechazo o cancelacion<textarea name="motivo_aplazamiento_rechazo" rows="2"><?= $e($row['motivo_aplazamiento_rechazo']) ?></textarea></label>
                                        <label data-decision-conditional="sin_fecha">Justificacion sin fecha objetivo<textarea name="justificacion_sin_fecha_objetivo" rows="2"><?= $e($row['justificacion_sin_fecha_objetivo']) ?></textarea></label>
                                        <label data-decision-conditional="verificacion">Observacion de verificacion<textarea name="observacion_verificacion" rows="2"><?= $e($row['observacion_verificacion']) ?></textarea></label>
                                        <label>Observaciones generales<textarea name="observaciones" rows="2"><?= $e($row['observaciones']) ?></textarea></label>
                                        <label data-decision-conditional="cambio">Motivo del cambio<textarea name="motivo_cambio" rows="2"><?= $e($row['motivo_cambio']) ?></textarea></label>
                                    <?php endif; ?>
                                    <div class="decision-actions">
                                        <span class="muted" data-decision-status></span>
                                        <button class="primary" type="submit">Guardar decision</button>
                                    </div>
                                </form>

                                <?php if (in_array('Posible riesgo pendiente de revisión humana', $alerts, true)): ?>
                                    <form class="decision-risk-form" method="post" action="<?= $e($basePath) ?>/protocolo-familiar/decisiones/<?= $e($row['codigo']) ?>/revision-riesgos" data-risk-review-form>
                                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                        <h3>Enviar a revision de riesgos</h3>
                                        <p class="muted">Prepara una propuesta. No crea un riesgo definitivo.</p>
                                        <div class="select-grid decision-grid">
                                            <label>Posible causa<textarea name="posible_causa" rows="2"></textarea></label>
                                            <label>Posible consecuencia<textarea name="posible_consecuencia" rows="2"><?= $e($row['riesgo_sugerido']) ?></textarea></label>
                                            <label>Responsable solicita<input name="responsable_solicita" value=""></label>
                                        </div>
                                        <label>Observaciones<textarea name="observaciones_riesgo" rows="2"></textarea></label>
                                        <div class="decision-actions"><span class="muted" data-risk-review-status><?= count($riskRequests) ?> propuestas registradas</span><button type="submit">Enviar a revision de riesgos</button></div>
                                    </form>
                                <?php endif; ?>

                                <details class="decision-history" <?= $isGuidedCat01 ? '' : 'open' ?>>
                                    <summary><h3>Historial</h3><small><?= count($history) ?> cambios</small></summary>
                                    <?php if ($history === []): ?><p class="muted">Sin cambios registrados todavia.</p><?php else: ?>
                                        <div class="decision-history-list">
                                            <?php foreach (array_slice($history, 0, 12) as $change): ?>
                                                <article>
                                                    <strong><?= $e($change['campo']) ?></strong>
                                                    <span><?= $e($change['valor_anterior']) ?> -> <?= $e($change['valor_nuevo']) ?></span>
                                                    <small><?= $e($change['usuario']) ?> · <?= $e($change['created_at']) ?><?= $change['motivo'] ? ' · ' . $e($change['motivo']) : '' ?></small>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </details>
                            </div>
                        </details>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
