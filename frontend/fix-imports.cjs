const fs = require('fs');
const path = require('path');

// Función para calcular la ruta relativa correcta
function getRelativePath(fromFile, toPath) {
  const fromDir = path.dirname(fromFile);
  const relativePath = path.relative(fromDir, toPath);
  return relativePath.replace(/\\/g, '/');
}

// Función para procesar un archivo
function processFile(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  let modified = false;
  
  const lines = content.split('\n');
  const newLines = lines.map(line => {
    if (line.includes("from '../../")) {
      // Mapear las rutas correctas basadas en la estructura del proyecto
      const srcPath = path.resolve(__dirname, 'src');
      const currentDir = path.dirname(filePath);
      
      if (line.includes("from '../../services/api'")) {
        const apiPath = path.resolve(srcPath, 'services/api');
        const relativePath = getRelativePath(filePath, apiPath);
        const newLine = line.replace("from '../../services/api'", `from '${relativePath}'`);
        if (newLine !== line) modified = true;
        return newLine;
      }
      
      if (line.includes("from '../../components/")) {
        const match = line.match(/from '\.\.\/\.\.\/components\/(.+)'/);
        if (match) {
          const componentPath = path.resolve(srcPath, 'components', match[1]);
          const relativePath = getRelativePath(filePath, componentPath);
          const newLine = line.replace(match[0], `from '${relativePath}'`);
          if (newLine !== line) modified = true;
          return newLine;
        }
      }
      
      if (line.includes("from '../../utils/cn'")) {
        const utilsPath = path.resolve(srcPath, 'utils/cn');
        const relativePath = getRelativePath(filePath, utilsPath);
        const newLine = line.replace("from '../../utils/cn'", `from '${relativePath}'`);
        if (newLine !== line) modified = true;
        return newLine;
      }
      
      if (line.includes("from '../../contexts/")) {
        const match = line.match(/from '\.\.\/\.\.\/contexts\/(.+)'/);
        if (match) {
          const contextPath = path.resolve(srcPath, 'contexts', match[1]);
          const relativePath = getRelativePath(filePath, contextPath);
          const newLine = line.replace(match[0], `from '${relativePath}'`);
          if (newLine !== line) modified = true;
          return newLine;
        }
      }
      
      if (line.includes("from '../../types'")) {
        const typesPath = path.resolve(srcPath, 'types');
        const relativePath = getRelativePath(filePath, typesPath);
        const newLine = line.replace("from '../../types'", `from '${relativePath}'`);
        if (newLine !== line) modified = true;
        return newLine;
      }
      
      if (line.includes("from '../../hooks/")) {
        const match = line.match(/from '\.\.\/\.\.\/hooks\/(.+)'/);
        if (match) {
          const hookPath = path.resolve(srcPath, 'hooks', match[1]);
          const relativePath = getRelativePath(filePath, hookPath);
          const newLine = line.replace(match[0], `from '${relativePath}'`);
          if (newLine !== line) modified = true;
          return newLine;
        }
      }
    }
    return line;
  });
  
  if (modified) {
    fs.writeFileSync(filePath, newLines.join('\n'));
    console.log(`Fixed imports in: ${filePath}`);
  }
}

// Función para recorrer directorios
function walkDir(dir) {
  const files = fs.readdirSync(dir);
  
  files.forEach(file => {
    const filePath = path.join(dir, file);
    const stat = fs.statSync(filePath);
    
    if (stat.isDirectory()) {
      walkDir(filePath);
    } else if (file.endsWith('.tsx') || file.endsWith('.ts')) {
      processFile(filePath);
    }
  });
}

// Ejecutar el script
const srcDir = path.join(__dirname, 'src');
walkDir(srcDir);
console.log('Import fixing completed!');