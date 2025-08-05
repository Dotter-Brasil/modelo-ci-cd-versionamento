<?php
header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input["message"] ?? "";

// Lê o arquivo de contexto
//$contextFile = "teste/bula_profissional.pdf.txt";
//$contextFile = $input["bula"]."txt";
$contextFile = "../../".$input["bula"];
$contextText = file_exists($contextFile) ? file_get_contents($contextFile) : "Sem contexto disponível.";


$apiKey = 'SUA_CHAVE_API_OPENAI';
$apiKey = file_get_contents("../../.env");

$data = [
    "model" => "gpt-4o",
    "messages" => [
        ["role" => "system", "content" => "Use o seguinte contexto para responder às perguntas:" . $contextText],
        ["role" => "user", "content" => $userMessage]
    ]
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $apiKey"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);
$chatResponse = $responseData["choices"][0]["message"]["content"] ?? "Erro ao obter resposta.";

echo json_encode(["response" => $chatResponse]);
?>