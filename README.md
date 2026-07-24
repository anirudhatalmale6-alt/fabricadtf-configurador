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
├── assets/
│   ├── configurator.css   # estilos (alinhados à marca fabricadtf.pt)
│   ├── configurator.js    # lógica do wizard, pré-visualização e preços
│   └── tshirt.svg
├── includes/              # (integração WordPress/WooCommerce — em desenvolvimento)
├── demo.html / index.html # demonstração autónoma do interface
└── sample_art.png
```

## Funcionalidades
- Wizard de 4 passos: **Modelo & Cor · Tamanhos · Personalização · Resumo**
- Cartões de produto com preços (Classic / Premium / Sport, editáveis)
- Amostras de cor que recolorem a t-shirt em tempo real
- Quantidades por tamanho (XS–XXL)
- Upload de arte (PNG, JPG, SVG, PDF) com **pré-visualização ao vivo** sobre a t-shirt
- Resumo do orçamento com IVA (23%)
- 100% responsivo (desktop e telemóvel), em português
- Painel de administração para gerir modelos/cores/preços *(no plugin WordPress)*

## Configuração
Todos os produtos, cores, tamanhos, IVA e preços são passados por `window.FDTF_CONFIG`
(ver `demo.html`). No WordPress, esses valores vêm do painel de administração.
