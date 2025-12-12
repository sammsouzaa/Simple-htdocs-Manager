# 📂 PHP Glass Explorer

Um gerenciador de arquivos moderno, *single-file* (arquivo único) e responsivo para substituir o index padrão do **localhost** (XAMPP, LAMPP, WAMP, etc).

Focado em design (Glassmorphism + Dark Mode) e usabilidade.

## ✨ Funcionalidades

- **Arquivo Único:** Basta soltar o `index.php` na pasta e pronto.
- **Visual Moderno:** Tema Dark/Light com efeito de vidro (Glassmorphism).
- **Preview de Arquivos:** Visualiza imagens, áudios, vídeos, PDFs e códigos sem sair da tela.
- **Segurança contra Travamentos:** Detecta arquivos binários pesados (.zip, .exe) e força o download ao invés de tentar ler o código.
- **Gestão de Arquivos:** Renomear e Excluir arquivos/pastas.
- **Diagnóstico de Permissão:** Ícones de cadeado 🔒 indicam quando o servidor não tem permissão de escrita.

## 🚀 Como usar

1. Baixe o arquivo `index.php` deste repositório.
2. Coloque-o na raiz do seu servidor local (ex: `C:\xampp\htdocs\` ou `/opt/lampp/htdocs/`).
3. Acesse `http://localhost` no seu navegador.

## ⚠️ Solução de Problemas (Linux/Mac)

Se você vir ícones de cadeado 🔒 ou receber erros de "Permissão Negada" ao tentar renomear/excluir, é porque o usuário do Apache (Daemon/Www-data) não tem permissão na sua pasta.

**Solução rápida (Terminal):**
Rode este comando para dar permissão total à pasta `htdocs`:

```bash
sudo chmod -R 777 /opt/lampp/htdocs
