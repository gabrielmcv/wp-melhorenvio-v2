# USEUP! Melhor Envio Customizacoes

Plugin separado para concentrar as customizacoes da USEUP! sobre o Melhor Envio e o WooCommerce, preservando a maior parte da logica fora do plugin original.

## O que ele faz

- adiciona uma tela em `WooCommerce > USEUP! Entrega`;
- permite cadastrar regras de dias extras com base em categorias, tags, classes de entrega, IDs de produto e SKUs;
- aplica as regras ao pacote usando `AND` ou `OR`, com modo `qualquer item` ou `todos os itens`;
- consolida regras pelo maior acrescimo ou pela soma;
- substitui o texto padrao de prazo por uma label amigavel:
  - ` (Chega até amanhã)`
  - ` (Chega até Segunda-feira)`
  - ` (Chega até Terça-feira)`
  - ` (Chega até Quarta-feira)`
  - ` (Chega até Quinta-feira)`
  - ` (Chega até Sexta-feira)`
  - ` (Chega até dd/mm)`
- oferece uma opcao para exibir `Entrega e prazo` direto na pagina do produto, com calculo por AJAX sem adicionar o item ao carrinho real;
- reaproveita o CEP informado pelo cliente para preencher WooCommerce session, `WC()->customer`, checkout e metadados do usuario quando aplicavel.

## Dependencias

- WooCommerce ativo;
- Melhor Envio ativo para os recursos especificos de prazo customizado e dias extras nas cotacoes do Melhor Envio;
- versao final do Melhor Envio com os hooks abaixo aplicados para as customizacoes de prazo e `$timeExtra`.

## Hooks necessarios no Melhor Envio

Foi necessario alterar o arquivo abaixo na versao final do plugin Melhor Envio:

- `Services/CalculateShippingMethodService.php`

Hooks adicionados:

- `useup_melhor_envio_time_extra`
- `useup_melhor_envio_delivery_deadline_label`

O plugin integrador agora verifica esse arquivo automaticamente e tenta reincluir esses hooks quando detectar que eles sumiram apos uma atualizacao do Melhor Envio.
Se a reinclusao automatica nao for possivel com seguranca, a tela `WooCommerce > USEUP! Entrega` passa a mostrar o status da integracao e o passo a passo para reinclusao manual.

## Como ativar

1. Garanta que o WooCommerce esteja ativo.
2. Garanta que o Melhor Envio esteja ativo e com os hooks acima no arquivo `Services/CalculateShippingMethodService.php`.
3. Ative o plugin `USEUP! Melhor Envio Customizacoes`.
4. Apos futuras atualizacoes do Melhor Envio, abra `WooCommerce > USEUP! Entrega` para conferir o painel de integracao caso queira validar o status manualmente.

## Como configurar

1. Acesse `WooCommerce > USEUP! Entrega`.
2. Ative ou mantenha desativada a opcao `Cálculo de frete na página do produto`.
3. Escolha o modo global:
   - usar apenas o maior acrescimo;
   - ou somar os acrescimos.
4. Cadastre uma ou mais regras.
5. Para cada regra, defina:
   - nome;
   - status;
   - dias extras;
   - operador `AND` ou `OR`;
   - aplicacao para `qualquer item` ou `todos os itens`;
   - condicoes por categoria, tag, classe de entrega, ID e/ou SKU.
6. Salve as configuracoes.

## Observacoes

- o bloco da pagina do produto usa os metodos disponiveis do WooCommerce e deixa o Melhor Envio responder normalmente quando ele estiver ativo;
- o calculo da pagina do produto nao adiciona o produto ao carrinho real;
- o CEP salvo pode ser reaproveitado no checkout pela mesma sessao ou pelo cadastro do usuario;
- regras sem nenhuma condicao salva nao sao aplicadas;
- o plugin nao altera preco do frete, pagamento, pedidos, produtos ou dados de clientes;
- feriados nacionais foram considerados na conta de dias uteis;
- feriados locais podem ser ajustados via filtro `useup_me_business_holidays`;
- o valor da mensagem de frete gratis pode ser ajustado pelo filtro `useup_me_free_shipping_threshold`.
- o plugin monitora o arquivo `CalculateShippingMethodService.php` do Melhor Envio, cria um backup antes da primeira reaplicacao automatica e registra o status dessa verificacao no painel administrativo.
