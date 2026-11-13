# Comprovantes de exemplo

Quatro comprovantes fictícios, desenhados por script e "fotografados" sobre uma mesa, para testar e demonstrar o Comprovante por foto. Empresas e pessoas são inventadas. Os CNPJs têm dígitos válidos e foram conferidos na consulta pública como não cadastrados, para não usar o número de nenhuma empresa real. O 04 é o 01 com o total alterado, para mostrar os pontos para conferir.

| Arquivo | O que é | Valor total | Data | Categoria esperada | O que torna difícil |
|---|---|---|---|---|---|
| `01-cupom-posto.jpg` | Cupom de posto | R$ 340,00 | 06/11/2026 | Combustível e transporte | subtotal, desconto e linha de tributos com outros valores |
| `02-recibo-oficina.jpg` | Recibo de oficina preenchido à mão | R$ 680,00 | 04/11/2026 | Manutenção | letra manuscrita, data por extenso |
| `03-pedido-ferragens.jpg` | Pedido de venda de loja de ferragens | R$ 1.220,27 | 09/11/2026 | Compra de equipamento | vários itens, desconto, ponto de milhar no total |
| `04-cupom-alterado.jpg` | O cupom 01 com o total trocado para R$ 840,00 | R$ 840,00 | 06/11/2026 | Combustível e transporte | subtotal e desconto não fecham com o total; se o 01 já estiver lançado, é a mesma foto |

Em 12/11/2026 a IA leu os três corretamente: valor, data, fornecedor e categoria.

Por serem gerados, não substituem fotos reais na eval (`evals/receipt/`).
