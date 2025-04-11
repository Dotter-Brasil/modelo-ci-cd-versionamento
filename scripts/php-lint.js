// Valida os arquivos PHP

const { execSync } = require("child_process");
const fs = require("fs");
const path = require("path");

const walk = (dir, ext = ".php", fileList = []) => {
  const files = fs.readdirSync(dir);
  files.forEach((file) => {
    const fullPath = path.join(dir, file);
    const stat = fs.statSync(fullPath);
    if (stat.isDirectory()) {
      walk(fullPath, ext, fileList);
    } else if (file.endsWith(ext)) {
      fileList.push(fullPath);
    }
  });
  return fileList;
};

try {
  const phpFiles = walk("./src/php");
  phpFiles.forEach((file) => {
    console.log(`Verificando ${file}`);
    execSync(`php -l ${file}`, { stdio: "inherit" });
  });
  console.log("✔️ Todos os arquivos PHP estão válidos.");
} catch (error) {
  console.error("❌ Erro ao validar arquivos PHP.");
  process.exit(1);
}
