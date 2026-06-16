# Dashboard - Portal Acadêmico para Moodle

Plugin local para Moodle que cria um dashboard centralizado agregando informações acadêmicas do estudante.

## Funcionalidades

### Cards Principais
- **Minhas Disciplinas** - Organizadas por categoria
- **Mensagens** - Conversas não lidas com auto-refresh
- **Calendário Acadêmico** - Anúncios e banners configuráveis

### Recursos
- Redirecionamento automático após login (opcional)
- Sistema de cache otimizado (5 min TTL)
- Auto-atualização de mensagens (30s)
- Suporte a HTML e imagens em anúncios
- Até 4 banners configuráveis
- Design responsivo

## Requisitos

- **Moodle**: 4.0+
- **PHP**: 8.0+

## Instalação

### Via Interface
1. `Administração → Plugins → Instalar plugins`
2. Upload do arquivo ZIP
3. Seguir assistente

### Via SSH
```bash
cd /path/to/moodle/local/
unzip dashboard.zip
chmod -R 755 dashboard/
```

## Configuração

**Acesso:** `Administração → Plugins → Plugins locais → Portal Acadêmico`

### Opções
- **Redirecionamento**: Habilitar/desabilitar após login
- **Anúncios**: Editor HTML com suporte a imagens
- **Banners**: Upload de até 4 imagens (JPG, PNG, GIF, WebP)

## Uso

Após instalação: `http://seu-moodle/local/dashboard/`

## Documentação Técnica

Para especificação completa, consulte `spec.md`

## Versão

**2025101401**  
Status: Estável

## Licença

MIT License

## Autor

© 2025