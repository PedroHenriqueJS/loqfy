# Loqfy

Sistema de gestão para locadoras de máquinas e ferramentas de construção: catálogo, clientes, orçamentos, locações, financeiro e um painel de indicadores. Tudo funciona à mão. Por cima disso há ferramentas de IA, opcionais, em quatro pontos do trabalho:

1. **Orçamento a partir do pedido.** O cliente escreve do jeito dele ("preciso de uma betoneira e 20 andaimes pro fim de semana, entrega no Campeche"). A IA lê, consulta o catálogo e deixa o orçamento montado para o atendente revisar e aprovar.
2. **Vistoria por foto.** O funcionário fotografa o equipamento na saída e na devolução. A IA compara as duas fotos e aponta possíveis avarias novas para ele conferir.

3. **Comprovante por foto.** O funcionário fotografa o cupom do posto ou o recibo da oficina. A IA lê fornecedor, data, valor e categoria e abre o lançamento preenchido no Financeiro, com a foto ao lado, para alguém conferir e salvar.
4. **Análise dos indicadores.** No painel inicial, um botão pede à IA uma leitura dos números do dia: riscos, oportunidades e o que fazer na semana.

O que a IA produz cai nas mesmas listas do sistema manual. Um orçamento montado pela IA aparece ao lado dos feitos à mão, com uma etiqueta de origem, e pode ser editado como qualquer outro.

Projeto enviado ao Futures, o programa de trainee da Softplan.

## De onde veio

Em 2024 fiz, como freelancer, um sistema de gestão para uma locadora de ferramentas no interior do Mato Grosso do Sul. A partir dele surgiu a ideia de um produto para qualquer locadora, que chamei de Loqfy. O MVP ficou pela metade. Este repositório retoma a ideia com outra pergunta: em que pontos do dia de uma locadora a IA tira trabalho de alguém sem tirar o controle dessa pessoa?

Os dados aqui são fictícios: uma locadora de demonstração na Grande Florianópolis, com 30 equipamentos e 4 zonas de entrega.

## Como o trabalho é dividido

A regra que organiza o sistema inteiro:

- **A IA interpreta.** Ela entende texto livre e fotos, que é o que código comum não faz.
- **O código calcula.** Preço, desconto, frete e disponibilidade saem do banco, por regras escritas em PHP.
- **A pessoa aprova.** Nenhum orçamento é enviado e nenhuma avaria é registrada sem a decisão de um funcionário.

A IA também é opcional. Um orçamento pode ser criado do zero pelo formulário, e uma devolução pode ser registrada sem foto. Os dois caminhos passam pelas mesmas regras de preço e estoque (`QuoteBuilder`), então o resultado não depende de quem preencheu.

Consequências práticas:

- O modelo nunca vê um preço. As ferramentas que ele consulta não devolvem valores, e qualquer número que ele escreva é ignorado. Há um teste automatizado para isso.
- A disponibilidade é conferida de novo pelo sistema depois da resposta da IA, e mais uma vez na hora de aprovar.
- Quando a IA troca um equipamento indisponível por uma alternativa, o pedido vai para revisão com a troca à vista.
- A tela separa o que foi conferido no sistema do que é observação da IA.
- Cada execução fica registrada com as consultas feitas, o tempo e os tokens. O atendente pode abrir "Como a IA leu este pedido".
- A análise do painel recebe só os indicadores agregados, com a instrução de não fazer contas. Depois da resposta, o sistema procura nos dados cada número que a IA escreveu. Um insight com número que não está lá aparece marcado como "número não conferido".

## Resultados medidos

Cada recurso de IA tem uma eval: um conjunto de entradas com resposta conhecida, rodado por um comando, que diz quanto a IA acerta e onde erra.

### Orçamento: 24 de 24 casos corretos, depois de uma correção

`php artisan loqfy:eval quote`, modelo `qwen/qwen3.8-27b`.

| Métrica | 11/11/2026 | 15/11/2026 |
|---|---|---|
| Casos 100% corretos | 22/24 (92%) | 24/24 (100%) |
| Resposta em formato válido | 24/24 | 24/24 |
| Equipamentos e quantidades corretos | 24/24 | 24/24 |
| Datas corretas | 23/24 | 24/24 |
| Zona de entrega correta | 24/24 | 24/24 |
| Sinal de "precisa de revisão" correto | 23/24 | 24/24 |
| Total em reais correto, quando conferido | 14/14 | 14/14 |
| Preços inventados | 0 | 0 |
| Tempo médio por pedido | 2,7 s | 2,5 s |

Os casos cobrem pedido simples, vários itens, datas relativas ("fim de semana", "amanhã e sexta"), gíria ("sapo", "makita", "helicóptero"), erro de digitação, item indisponível, pedido ambíguo, item fora do catálogo e mensagem que não é pedido.

Os dois erros da primeira execução, e o que mudou:

- **"quanto fica uma betoneira pra semana que vem?".** A IA assumiu segunda a domingo, ou seja, inventou datas que o cliente não disse. Foi o erro mais sério. A instrução ganhou uma regra: referência vaga, sem dias definidos ("semana que vem", "mês que vem", "uns dias"), deixa o período em aberto e pede os dias ao cliente.
- **"preciso de um lava jato e de uma makita sábado".** Itens, data e total certos, mas a IA marcou para revisão por achar que "makita" (uma marca) podia não ser a serra mármore. "makita" está cadastrado como outro nome da serra, mas a IA não via esse cadastro. A busca passou a devolver os outros nomes de cada equipamento, e a instrução diz que um termo cadastrado não é ambíguo.

Um cuidado ao ler o 24 de 24: as duas correções foram escritas depois de ver os erros, nos mesmos 24 casos. O resultado mostra que os erros foram resolvidos, não que a IA acerta tudo. Para medir se a melhoria vale para pedidos novos, o próximo passo é um conjunto separado de casos escritos sem olhar o resultado.

Os casos e os gabaritos estão em `evals/quote.json`. O resultado completo, caso a caso, está em `evals/results/quote-latest.json` e na tela "Qualidade da IA".

### Vistoria por foto: eval pendente

Antes de construir a tela, testei o modelo com quatro pares de fotos públicas:

| Caso | Esperado | Resultado |
|---|---|---|
| Mesmo objeto em dois ângulos, sem dano novo | nenhuma avaria | acertou |
| Betoneira gasta comparada com ela mesma | nenhuma avaria | acertou, tratou a tinta descascada como desgaste anterior |
| Duas betoneiras diferentes | avisar que não é o mesmo equipamento | acertou |
| Foto única de uma escada de madeira quebrada | degraus quebrados | errou: descreveu como estrutura metálica, com confiança alta |

O teste mostrou que o modelo não gera falso alarme em equipamento velho, e que a confiança que ele declara não serve para decidir nada. Por isso a tela não usa esse campo: o funcionário sempre confirma.

Falta a medição que importa: pares reais do mesmo equipamento, com e sem dano novo. O comando já existe (`php artisan loqfy:eval inspection`) e o formato dos casos está em `evals/inspection/README.md`. **PENDENTE: rodar com os pares de fotos e registrar aqui avarias detectadas, avarias perdidas e falsos alarmes.**

### Análise dos indicadores: sem eval ainda

A conferência de números pega valor inventado, mas não pega relação inventada. Em um teste, a IA citou corretamente R$ 395,00 em atraso e uma devolução atrasada de um cliente, e juntou os dois como se o valor fosse desse cliente. Os dados não diziam isso. Passei a enviar a lista de contas em atraso com a descrição de cada uma, o que dá à IA o fato certo, mas esse tipo de erro continua possível. Por isso a análise só roda quando o usuário pede, mostra o modelo e a hora, e avisa que precisa ser conferida.

### Pontos para conferir: sem IA

Verificar se uma foto foi gerada ou editada por IA não é algo que um modelo como este faz com segurança, e um falso positivo acusa um cliente honesto. Em vez disso, o sistema confere com código, e mostra o resultado como pontos para conferir, nunca como veredito:

- **Mesma foto usada de novo.** Cada foto recebe uma impressão digital (dHash de 64 bits). A mesma imagem, mesmo reduzida ou salva de novo, fica a poucos bits de distância. O sistema avisa quando a foto de um comprovante ou de uma vistoria já foi usada em outro lançamento ou locação, e quando a foto de devolução é a mesma da saída.
- **Comprovante que não fecha.** CNPJ do emissor com dígito verificador inválido, data no futuro, subtotal menos desconto diferente do total, e despesa de mesmo valor e data já lançada.

Para isso a IA passou a copiar também o CNPJ, o subtotal e o desconto como impressos; as contas são do código. `exemplos/comprovantes/04-cupom-alterado.jpg` é um cupom com o total trocado, para ver os avisos aparecerem.

### Comprovante por foto: eval pendente

A IA copia o valor total como está impresso ("1.234,56"), e o código transforma isso em número. Assim um separador de milhar nunca vira casa decimal no caminho; essa conversão tem 9 testes automatizados. Num cupom de posto gerado para teste, com subtotal, desconto e total, ela leu o total certo, a data, o fornecedor e a categoria.

Falta a medição com comprovantes reais. O comando existe (`php artisan loqfy:eval receipt`) e o formato dos casos está em `evals/receipt/README.md`. **PENDENTE: rodar com fotos de comprovantes e registrar aqui.**

## O que deu errado no caminho

**O agente em laço.** A primeira versão era um agente livre: o modelo chamava ferramentas até decidir responder. Com este modelo, ele repetia a mesma busca até esgotar os passos, sem nunca responder. Ajustar o prompt não resolveu. Remover as ferramentas no meio da conversa também não: ele escrevia a chamada de ferramenta como texto. A solução foi trocar o laço por duas chamadas em ordem fixa:

1. **Consulta**, com ferramentas: o modelo lê o pedido e pede, de uma vez, as buscas de catálogo e a zona de entrega.
2. **Resposta**, sem ferramentas e em modo JSON: o modelo recebe o pedido e os resultados em texto e escreve os dados finais.

O fluxo ficou previsível e não tem como entrar em laço.

**O limite de tokens.** O plano gratuito do Groq permite 8.000 tokens por minuto. Com busca e disponibilidade em ferramentas separadas, cada pedido custava três chamadas e cerca de 6.000 tokens, ou um pedido por minuto. Juntei as duas em uma ferramenta (`search_equipment` já devolve a disponibilidade no período e as alternativas). Cada pedido passou a custar duas chamadas e cerca de 3.000 tokens.

**Aritmética de datas.** Modelos erram dia da semana. O prompt leva o calendário dos próximos 14 dias escrito por extenso, e as datas relativas da eval ficaram certas em todos os casos menos um.

## Limites

- A IA de vistoria enxerga só dano visível na foto. Defeito interno, peça que parou de funcionar e trinca fina ficam de fora.
- A comparação depende de fotos parecidas. Ângulo ou luz muito diferentes pioram o resultado.
- O orçamento depende do catálogo ter os apelidos que os clientes usam. Um termo novo ("bambolê" para alisadora) precisa ser cadastrado.
- Com o limite gratuito, o sistema atende cerca de dois pedidos por minuto. Uma locadora real precisaria de um plano pago ou de outro provedor.
- A eval tem 24 casos escritos por mim. Ela mostra que o sistema funciona nos padrões que previ; pedidos reais vão trazer padrões que não previ.
- Onde a regra é exata (preço, estoque, frete, fluxo de caixa), a IA não é usada.
- Uma locação só é aprovada para cliente cadastrado com documento conferido. Para pessoas, o sistema registra só que o documento foi visto, sem guardar número nem imagem: guardar RG ou CNH exigiria controle de acesso e prazo de retenção que o sistema ainda não tem. Para empresas, o CNPJ preenche razão social, endereço e telefone a partir da Receita Federal (via BrasilAPI), sem IA, porque o dado é exato.
- Não há contas por usuário nem permissões. Todos entram como o mesmo usuário de demonstração e veem os mesmos dados. Separar os dados por locadora e limitar o que cada perfil acessa é o passo que falta para virar um SaaS de verdade.
- O histórico de locações e despesas da demonstração é gerado por um seeder, para os gráficos terem o que mostrar. Não são dados reais.

## Próximo passo: licitações

Locadoras maiores vendem para prefeituras, e um edital de locação de equipamentos é um documento longo, com a lista de itens no termo de referência. A mesma busca de catálogo que serve ao pedido de texto serviria para ler um edital, cruzar os itens com o estoque e mostrar o que a locadora consegue atender. Isto é uma hipótese de mercado, ainda sem validação com locadoras, e não foi construído.

## Arquitetura

- **Backend:** Laravel 13 (PHP 8.5), SQLite, fila no banco.
- **IA:** [Prism](https://prismphp.com) falando com o Groq, modelo `qwen/qwen3.8-27b` para texto e imagem. O provedor e o modelo ficam no `.env`.
- **Frontend:** React 19, TypeScript, MUI e Vite, em `frontend/`.

```
app/Ai/                 QuoteAgent, InspectionAnalyzer, ReceiptReader, InsightsAnalyzer,
                        Tools/QuoteTools, ProviderRetry
app/Services/           regras de negócio: CatalogSearch, AvailabilityService, PricingService,
                        DeliveryZoneResolver, QuoteBuilder, RentalService, InspectionService,
                        ReceiptIntake, ReceiptChecks, DuplicatePhotos, CnpjLookup,
                        FinanceReport, DashboardReport, AiRunLog
app/Http/Controllers/   recebem a requisição e chamam os serviços
app/Http/Requests/      validação das entradas
app/Http/Resources/     formato do JSON devolvido
app/Jobs/               ProcessRentalRequest, AnalyzeInspection
app/Console/Commands/   LoqfyEval
evals/                  casos, gabaritos e resultados das evals
tests/                  regras de negócio e fluxos de IA com respostas simuladas
frontend/src/pages/     Início, Orçamentos, Locações, Clientes, Catálogo e Financeiro (sistema manual)
frontend/src/pages/ai/  Orçamento por mensagem, Vistoria por foto, Comprovante por foto e Qualidade da IA
```

Caminho de um pedido lido pela IA (o orçamento manual entra direto no passo 3, por `POST /api/quotes`):

1. `POST /api/rental-requests` grava o texto e põe `ProcessRentalRequest` na fila.
2. `QuoteAgent` faz as duas chamadas ao modelo e devolve itens, quantidades, datas e bairro.
3. `QuoteBuilder` monta o orçamento com preços do banco, confere a disponibilidade e lista os problemas.
4. O atendente edita se precisar. Cada edição passa de novo pelo `QuoteBuilder`.
5. Ao aprovar, `RentalService` confere o estoque dentro de uma transação, cria a locação e lança a conta a receber no financeiro.

A página pública (`frontend/src/landing/`) apresenta o produto e leva à entrada. O acesso é por um usuário de demonstração, sem senha: é uma sessão real do Laravel, pronta para receber contas com credenciais. As quatro rotas que chamam o modelo têm limite de 10 chamadas por minuto por visitante e 300 por dia no total, para proteger a cota gratuita do provedor.

Cada locação gera o contrato em PDF (`ContractController`, modelo em `resources/views/contracts/rental.blade.php`), com os dados da locadora, do cliente, dos equipamentos, do período e dos valores, mais as cláusulas de vistoria, atraso e responsabilidade. O que o sistema não guarda de propósito, como o CPF de uma pessoa, sai como linha em branco para preencher na assinatura. Os dados da locadora ficam no `.env` (`LOQFY_COMPANY_*`).

No celular, a barra lateral vira um menu, os orçamentos viram cartões, as tabelas rolam dentro do próprio quadro e os formulários abrem em tela cheia. As fotos são convertidas e reduzidas no navegador antes do envio, inclusive HEIC do iPhone.

A interface tem modo claro e escuro, e os gráficos são componentes próprios em SVG, com as cores conferidas para daltonismo e contraste nos dois modos.

## Como rodar

Requisitos: PHP 8.3 ou mais novo, Composer, Node 22 ou mais novo e uma chave gratuita do [Groq](https://console.groq.com).

```bash
composer install
cp .env.example .env
php artisan key:generate
# ponha a chave em GROQ_API_KEY no .env
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link

cd frontend && npm install && cd ..
```

Em três terminais:

```bash
php artisan serve          # API em http://localhost:8000
php artisan queue:work     # processa os pedidos e as vistorias
cd frontend && npm run dev # interface em http://localhost:5173
```

Testes e evals:

```bash
php artisan test                  # 40 testes, sem chamar a IA
php artisan loqfy:eval quote      # cerca de 9 minutos, por causa do limite de tokens
php artisan loqfy:eval inspection
php artisan loqfy:eval receipt
```

Para servir tudo pelo Laravel, rode `npm run build` em `frontend/`. A interface passa a abrir em `http://localhost:8000`.

## Como foi construído

Desenvolvi o projeto com o Claude Code como par de programação: desenho, código, testes e a investigação dos problemas descritos acima. As decisões de produto e a revisão do que entrou são minhas.
