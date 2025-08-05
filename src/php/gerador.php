<?php
// codigo para geracao autonoma de documentacao do sistema

set_time_limit(0);
header('Content-Type: text/plain');
header('X-Accel-Buffering: no'); // Para streaming imediato (Nginx)
@ob_flush(); @flush();

// CONFIG
$apiKey = 'SUA_CHAVE_API_OPENAI';
$apiKey = file_get_contents("../../.env");
$model = 'gpt-4o'; // ou gpt-4, gpt-4.5-turbo

$arquivoInicial = $_GET['arquivo'] ?? 'index.html';
$caminhoInicial = realpath("../../$arquivoInicial");

if (!$caminhoInicial || !file_exists($caminhoInicial)) {
    echo "Arquivo inicial não encontrado: $arquivoInicial\n";
    exit;
}


// Pasta de armazenamento de documentacao
$docs = '../../documentacao/';
if (!is_dir($docs)) mkdir($docs, 0755, true);



function chamarChatGPT($mensagens, $apiKey, $model) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => $model,
        'messages' => $mensagens,
        'temperature' => 0.3
    ];
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        echo "❌ Erro ao chamar API do ChatGPT\n";
        echo "Código HTTP: $httpCode\n";
        echo "Resposta da API:\n$response\n";
        exit(1);
    }

    $json = json_decode($response, true);
    return $json;
}

function corrigirMermaidClassDiagram($markdown) {
    return preg_replace_callback('/```mermaid\s*classDiagram(.*?)```/is', function ($match) {
        $conteudo = trim($match[1]);
        $linhas = explode("\n", $conteudo);
        $classes = [];
        $relacoes = [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (preg_match('/^(\w+)\s*[-.]+[-><]*\s*(\w+)\s*:?(.+)?$/', $linha, $m)) {
                $from = $m[1]; $to = $m[2]; $label = trim($m[3] ?? '');
                $relacoes[] = "$from --> $to" . ($label ? " : $label" : '');
                continue;
            }
            if (preg_match('/^(\w+)\s*:\s*(\+?\w+\(.*\))$/', $linha, $m)) {
                $classe = $m[1]; $metodo = preg_replace('/<([^<>]+)>/', '$1', $m[2]);
                $classes[$classe][] = $metodo;
                continue;
            }
        }

        $output = "```mermaid\nclassDiagram\n";
        foreach ($classes as $classe => $metodos) {
            $output .= "    class $classe {\n";
            foreach ($metodos as $m) $output .= "        $m\n";
            $output .= "    }\n";
        }
        foreach ($relacoes as $r) $output .= "    $r\n";
        $output .= "```\n";
        return $output;
    }, $markdown);
}

// === ETAPA 1: IDENTIFICAR ARQUIVOS ===
$conteudoInicial = file_get_contents($caminhoInicial);
echo "Analisando: $arquivoInicial\n";
@ob_flush(); @flush();

$mensagemInicial = [
    ['role' => 'system', 'content' => 'Você é um analista de sistemas experiente. Dado um HTML, identifique todos os arquivos (JS, CSS, PHP) que ele referencia.'],
    ['role' => 'user', 'content' => "$arquivoInicial:\n```html\n$conteudoInicial\n```"]
];

$resposta = chamarChatGPT($mensagemInicial, $apiKey, $model);
preg_match_all('/[\w\-\/]+\.(php|js|css|html)/i', $resposta['choices'][0]['message']['content'] ?? '', $matches);
$arquivos = array_unique(array_merge([$arquivoInicial], $matches[0]));


// === ETAPA 1: IDENTIFICAR ARQUIVOS DIRETOS ===
$conteudoInicial = file_get_contents($caminhoInicial);
echo "Analisando: $arquivoInicial\n";
@ob_flush(); @flush();

$mensagemInicial = [
    ['role' => 'system', 'content' => 'Você é um analista de sistemas experiente. Dado um HTML, identifique todos os arquivos (JS, CSS, PHP) que ele referencia diretamente.'],
    ['role' => 'user', 'content' => "$arquivoInicial:\n```html\n$conteudoInicial\n```"]
];

$resposta = chamarChatGPT($mensagemInicial, $apiKey, $model);
preg_match_all('/[\w\-\/]+\.(php|js|css|html)/i', $resposta['choices'][0]['message']['content'] ?? '', $matches);
$arquivos = array_unique(array_merge([$arquivoInicial], $matches[0]));

// === ETAPA 1.5: IDENTIFICAR DEPENDÊNCIAS INDIRETAS COM CHATGPT ===
// === ETAPA 1.5: IDENTIFICAR DEPENDÊNCIAS INDIRETAS COM CHATGPT ===
$visitados = [];
$pilha = $arquivos;

while (!empty($pilha)) {
    $arquivoAtual = array_shift($pilha);
    if (in_array($arquivoAtual, $visitados)) continue;

    $caminho = realpath("../../$arquivoAtual");
    if (!$caminho || !file_exists($caminho)) {
        echo "❌ Arquivo não encontrado: $arquivoAtual\n";
        continue;
    }

    echo "🔍 Buscando referências em: $arquivoAtual\n";
    $conteudo = file_get_contents($caminho);
    $extensao = pathinfo($arquivoAtual, PATHINFO_EXTENSION);

    $mensagem = [
        ['role' => 'system', 'content' => "Você é um engenheiro de software. Liste os arquivos (PHP, JS, CSS, JSON, etc) que o conteúdo a seguir referencia, carrega ou importa por qualquer meio. Dê apenas os nomes dos arquivos em uma lista, separados por vírgula. Não explique."],
        ['role' => 'user', 'content' => "$arquivoAtual:\n```$extensao\n$conteudo\n```"]
    ];

    $resposta = chamarChatGPT($mensagem, $apiKey, $model);

    // Extrai arquivos da resposta do ChatGPT
    preg_match_all('/[\w\-\/]+\.(php|js|css|html|json)/i', $resposta['choices'][0]['message']['content'] ?? '', $m);
    $novosArquivos = array_unique($m[0]);

    // Ajustar caminhos e adicionar à pilha
    foreach ($novosArquivos as $novo) {
        $ext = pathinfo($novo, PATHINFO_EXTENSION);
        $base = basename($novo);

        // Corrigir caminho conforme a estrutura do projeto
        switch ($ext) {
            case 'js':
                $novoCorrigido = "src/js/$base";
                break;
            case 'php':
                $novoCorrigido = "src/php/$base";
                break;
            case 'css':
                $novoCorrigido = "css/$base";
                break;
            case 'html':
                $novoCorrigido = $base;
                break;
            default:
                $novoCorrigido = $novo;
        }

        if (!in_array($novoCorrigido, $arquivos)) {
            echo "➕ Novo arquivo identificado: $novoCorrigido\n";
            $arquivos[] = $novoCorrigido;
            $pilha[] = $novoCorrigido;
        }
    }

    $visitados[] = $arquivoAtual;
    @ob_flush(); @flush();
}



// === ETAPA 2: ANALISAR ARQUIVOS INDIVIDUALMENTE ===
$documentacaoCompleta = "";
$arquivosDocumentados = [];

foreach ($arquivos as $arquivo) {
    $caminho = realpath("../../$arquivo");
    echo "Processando: $arquivo...\n";
    @ob_flush(); @flush();

    if (!$caminho || !file_exists($caminho)) {
        echo "Arquivo não encontrado: $arquivo\n";
        continue;
    }

    $conteudo = file_get_contents($caminho);
    $extensao = pathinfo($arquivo, PATHINFO_EXTENSION);
    $mensagem = [
        ['role' => 'system', 'content' => "Você é um engenheiro de software sênior. Gere uma documentação clara e completa (com base em GAMP5, FDA 21 CFR Part11 e ANVISA), mas sem repetir blocos de código na documentação. Em vez disso, comente e ilustre as funcionalidades com base em trechos e resumos. Inclua JSDoc ou PHPDoc visual e explicativo para cada função, além de diagramas mermaid (flowchart, classDiagram, usecase)."],
        ['role' => 'user', 'content' => "$arquivo:\n```$extensao\n$conteudo\n```"]
    ];

    $resposta = chamarChatGPT($mensagem, $apiKey, $model);
    $conteudoArquivo = $resposta['choices'][0]['message']['content'] ?? '';
    $documentacaoCompleta .= "## Arquivo: $arquivo\n\n$conteudoArquivo\n\n---\n";
    $arquivosDocumentados[] = $arquivo;

    $arquivoParcial = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($arquivo));
    file_put_contents($docs . "/documentacao_bruta_{$arquivoParcial}.md", $conteudoArquivo);
    echo "✅ Documentado: $arquivo\n";
    @ob_flush(); @flush();
}

// === ETAPA 3: DOCUMENTAÇÃO FINAL ===
echo "Gerando documentação final...\n";
$indiceMarkdown = implode("\n", array_map(fn($a) => "- [$a](#arquivo-" . strtolower(str_replace('.', '', basename($a))) . ")", $arquivosDocumentados));

$arquivosParciais = glob($docs . "/documentacao_bruta_*.md");
sort($arquivosParciais); // Para manter ordem alfabética ou lógica

$documentacaoCompleta = "";
foreach ($arquivosParciais as $arquivoParcial) {
    $documentacaoCompleta .= "\n\n<!-- INÍCIO: " . basename($arquivoParcial) . " -->\n";
    $documentacaoCompleta .= file_get_contents($arquivoParcial);
    $documentacaoCompleta .= "\n<!-- FIM: " . basename($arquivoParcial) . " -->\n";
}


$mensagemFinal = [
    //['role' => 'system', 'content' => "Você é um especialista em documentação técnica para sistemas regulados. Use o conteúdo abaixo para compor um documento final em Markdown estruturado para GitHub, com foco em qualidade, clareza e compatibilidade com GAMP5, ANVISA e FDA 21 CFR Part 11.\n\nInclua as seções:\n1. Título e Introdução\n2. Índice com links internos\n3. Lista de arquivos documentados\n4. Seções por arquivo com comentários explicativos, PHPDoc/JSDoc visual e diagramas mermaid (flowchart, classDiagram, usecase)\n5. Conclusão e recomendações."],
    ['role' => 'system', 'content' => "Você é um engenheiro de software sênior e especialista em documentação técnica. Sua tarefa é gerar uma documentação final em Markdown compatível com o GitHub, altamente estruturada, clara e profissional, adequada para auditorias regulatórias (GAMP 5, ANVISA, FDA 21 CFR Part 11).\n\nInclua as seguintes seções:\n\n1. Título e Introdução Geral\n2. Índice com links internos\n3. Lista de Arquivos Documentados\n4. Seções por arquivo com:\n   - Comentários técnicos explicativos\n   - Documentação inline (JSDoc ou PHPDoc)\n   - Diagramas de fluxo (Mermaid flowchart)\n   - Diagrama de classes (Mermaid classDiagram), se aplicável\n   - Diagrama de caso de uso (Mermaid usecase), se aplicável\n5. Conclusão e recomendações\n\nMantenha os diagramas formatados corretamente com ```mermaid. Não resuma. Use o conteúdo bruto abaixo como base.\n\nInicie agora:"],
    ['role' => 'user', 'content' => $documentacaoCompleta]
];

$respostaFinal = chamarChatGPT($mensagemFinal, $apiKey, $model);
$documentacaoFinal = $respostaFinal['choices'][0]['message']['content'] ?? '';

$sufixo = pathinfo($arquivoInicial, PATHINFO_FILENAME);
$caminhoFinal = $docs . "/documentacao_{$sufixo}.md";

$documentacaoFinal = corrigirMermaidClassDiagram($documentacaoFinal);
file_put_contents($caminhoFinal, $documentacaoFinal);
file_put_contents($docs . "/documentacao_bruta_{$sufixo}.md", $documentacaoCompleta);

echo "\n📁 Documentação final salva em: documentacao_{$sufixo}.md\n";
?>