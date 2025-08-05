<?php

// busca todos os arquivos html na raiz

$arquivos = glob('../../*.html');
$nomes = array_map('basename', $arquivos);
header('Content-Type: application/json');
echo json_encode($nomes);
?>