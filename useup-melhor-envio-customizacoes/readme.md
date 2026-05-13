# USEUP! Melhor Envio Customizacoes

Plugin separado para concentrar as customizações da USEUP! sobre o Melhor Envio e o WooCommerce, preservando a maior parte da lógica fora do plugin original.

## O que ele faz

- adiciona uma tela em `WooCommerce > USEUP! Entrega`;
- permite cadastrar regras de dias extras com base em categorias, tags, classes de entrega, IDs de produto e SKUs;
- aplica as regras ao pacote usando `AND` ou `OR`, com modo `qualquer item` ou `todos os itens`;
- consolida regras pelo maior acréscimo ou pela soma;
- substitui o texto padrão de prazo por uma label amigável:
  - ` (Chega até amanhã)`
  - ` (Chega até Segunda-feira)`
  - ` (Chega até Terça-feira)`
  - ` (Chega até Quarta-feira)`
  - ` (Chega até Quinta-feira)`
  - ` (Chega até Sexta-feira)`
  - ` (Chega até dd/mm)`
- oferece uma opção para exibir `Entrega e prazo` direto na página do produto, com cálculo por AJAX sem adicionar o item ao carrinho real;
- permite configurar no admin o valor e o texto da mensagem de frete grátis na página do produto, com suporte ao placeholder `{amount}`;
- aplica um polish opcional na página de produto com preço atacado/varejo, badges discretas, tooltip em `no atacado` e short description expansível;
- oferece a opção `Aplicar visual premium no checkout`, que melhora de forma pontual a apresentação de frete, total, tags lisas, pagamento e botão final sem alterar a estrutura real do checkout;
- reaproveita o CEP informado pelo cliente para preencher WooCommerce session, `WC()->customer`, checkout e metadados do usuário quando aplicável.

## Dependencias

- WooCommerce ativo;
- Melhor Envio ativo para os recursos específicos de prazo customizado e dias extras nas cotações do Melhor Envio;
- versão final do Melhor Envio com os hooks abaixo aplicados para as customizações de prazo e `$timeExtra`.

## Hooks necessarios no Melhor Envio

Foi necessário alterar o arquivo abaixo na versão final do plugin Melhor Envio:

- `Services/CalculateShippingMethodService.php`

Hooks adicionados:

- `useup_melhor_envio_time_extra`
- `useup_melhor_envio_delivery_deadline_label`

O plugin integrador agora verifica esse arquivo automaticamente e tenta reincluir esses hooks quando detectar que eles sumiram após uma atualização do Melhor Envio.
Se a reinclusão automática não for possível com segurança, a tela `WooCommerce > USEUP! Entrega` passa a mostrar o status da integração e o passo a passo para reinclusão manual.

## Como ativar

1. Garanta que o WooCommerce esteja ativo.
2. Garanta que o Melhor Envio esteja ativo e com os hooks acima no arquivo `Services/CalculateShippingMethodService.php`.
3. Ative o plugin `USEUP! Melhor Envio Customizacoes`.
4. Após futuras atualizações do Melhor Envio, abra `WooCommerce > USEUP! Entrega` para conferir o painel de integração caso queira validar o status manualmente.

## Como configurar

1. Acesse `WooCommerce > USEUP! Entrega`.
2. Ative ou mantenha desativada a opção `Cálculo de frete na página do produto`.
3. Defina, se quiser, o valor de referência e o texto da mensagem de frete grátis exibida nesse bloco.
4. Ative ou desative a opção `Aplicar visual premium no checkout`.
5. Ative ou desative a opção `Aplicar visual premium na página de produto`.
6. Ajuste, se quiser, os textos das badges `Até 12x` e `5% no PIX`.
7. Escolha o modo global:
   - usar apenas o maior acréscimo;
   - ou somar os acréscimos.
8. Cadastre uma ou mais regras.
9. Para cada regra, defina:
   - nome;
   - status;
   - dias extras;
   - operador `AND` ou `OR`;
   - aplicação para `qualquer item` ou `todos os itens`;
   - condições por categoria, tag, classe de entrega, ID e/ou SKU.
10. Salve as configurações.

## Observacoes

- o bloco da página do produto usa os métodos disponíveis do WooCommerce e deixa o Melhor Envio responder normalmente quando ele estiver ativo;
- o polish da página de produto usa apenas hooks do WooCommerce e assets do plugin, sem alterar tema ou templates do Melhor Envio;
- o cálculo da página do produto não adiciona o produto ao carrinho real;
- o CEP salvo pode ser reaproveitado no checkout pela mesma sessão ou pelo cadastro do usuário;
- regras sem nenhuma condição salva não são aplicadas;
- o plugin não altera preço do frete, pagamento, pedidos, produtos ou dados de clientes;
- o visual premium do checkout atua só na apresentação, preserva a estrutura real do WooCommerce e continua compatível com o refresh AJAX do checkout;
- feriados nacionais foram considerados na conta de dias úteis;
- feriados locais podem ser ajustados via filtro `useup_me_business_holidays`;
- o valor da mensagem de frete grátis pode ser ajustado pelo filtro `useup_me_free_shipping_threshold`;
- o texto da mensagem de frete grátis pode ser ajustado pelo filtro `useup_me_free_shipping_message`;
- o plugin monitora o arquivo `CalculateShippingMethodService.php` do Melhor Envio, cria um backup antes da primeira reaplicação automática e registra o status dessa verificação no painel administrativo.
