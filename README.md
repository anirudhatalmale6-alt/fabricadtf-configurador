# Fábrica DTF — Configurador de T-shirts

Configurador de t-shirts personalizadas para o site **fabricadtf.pt** (WordPress + WooCommerce).
Fluxo tipo *tshirtpro.pt*: escolher modelo → cor → tamanhos/quantidades → upload da arte com
pré-visualização em tempo real → adicionar ao carrinho e finalizar no checkout existente.

## Demo interativa (protótipo do interface)
👉 Abre o `index.html` num browser, ou vê a versão publicada em GitHub Pages.

> A demo é apenas o **frontend** (sem WordPress). A integração real com o WooCommerce
> (carrinho, pagamentos, envios já configurados) é feita pelo plugin — ver abaixo.

## Estrutura
```
fabricadtf-configurador/
├── fabricadtf-configurador.php   # ficheiro principal do plugin WordPress
├── includes/
│   ├── class-fdtf-plugin.php     # shortcode, carregamento de assets, injeção de config
│   ├── class-fdtf-settings.php   # painel de administração (modelos/cores/preços)
│   └── class-fdtf-cart.php       # integração WooCommerce (carrinho, preço, encomenda, upload)
├── assets/
│   ├── configurator.css          # estilos (alinhados à marca fabricadtf.pt)
│   ├── configurator.js           # lógica do wizard, pré-visualização e preços
│   └── tshirt.svg
├── demo.html / index.html        # demonstração autónoma do interface
└── sample_art.png
```

## Instalação (WordPress)
1. Copiar a pasta `fabricadtf-configurador` para `wp-content/plugins/` (ou instalar o ZIP).
2. Ativar o plugin **Fábrica DTF – Configurador de T-shirts** (requer WooCommerce ativo).
3. Em **WooCommerce → Configurador T-shirts**, definir modelos, cores, tamanhos, IVA e preços.
4. Criar/editar a página do configurador e inserir o shortcode **`[fabricadtf_configurador]`**.

## Funcionalidades
- Wizard de 4 passos: **Modelo & Cor · Tamanhos · Personalização · Resumo**
- Cartões de produto com preços (editáveis no painel de administração)
- Amostras de cor que recolorem a t-shirt em tempo real
- Quantidades por tamanho (XS–XXL, configurável)
- Upload de arte (PNG ou PDF) com **pré-visualização ao vivo** sobre a t-shirt
- Resumo do orçamento com IVA (23%)
- 100% responsivo (desktop e telemóvel), em português
- Adiciona ao carrinho do WooCommerce e usa os **pagamentos e envios já configurados**
- Preço calculado no servidor (não confia no valor do cliente) e guardado na encomenda
- Painel de administração para gerir modelos/cores/preços — sem código

## Configuração
No WordPress, os produtos, cores, tamanhos, IVA e preços vêm do painel de administração
e são injetados em `window.FDTF_CONFIG`. A `demo.html` usa um `FDTF_CONFIG` estático
apenas para demonstração do interface.
