@php
    $money = fn (float $value) => 'R$ '.number_format($value, 2, ',', '.');
    $date = fn ($value) => $value->format('d/m/Y');
    $cnpj = fn (?string $d) => $d ? substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12) : null;
    $isCompany = $customer?->kind === 'company';
    $months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $lateFee = $company['late_fee_multiplier'] == 1 ? 'uma diária' : number_format($company['late_fee_multiplier'], 1, ',', '.').' diária(s)';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Contrato de locação nº {{ $rental->id }}</title>
<style>
    @page { margin: 34mm 20mm 26mm 20mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.2pt; line-height: 1.42; color: #1b2233; }
    header { position: fixed; top: -24mm; left: 0; right: 0; height: 16mm; border-bottom: 1px solid #c9d1e0; }
    header .brand { font-size: 14pt; font-weight: bold; color: #0f1a33; }
    header .brand span { color: #f28c1e; }
    header .meta { position: absolute; right: 0; top: 2mm; text-align: right; font-size: 8.5pt; color: #5a6682; }
    footer { position: fixed; bottom: -16mm; left: 0; right: 0; font-size: 7.5pt; color: #8a94ab; border-top: 1px solid #e2e7f1; padding-top: 2mm; }
    footer .page:after { content: counter(page); }
    h1 { font-size: 13pt; margin: 0 0 4mm; letter-spacing: 0.02em; }
    h2 { font-size: 9.6pt; margin: 4.5mm 0 1.6mm; text-transform: uppercase; letter-spacing: 0.06em; color: #0f1a33; }
    p { margin: 0 0 2.2mm; text-align: justify; }
    .parties td { vertical-align: top; width: 50%; padding: 3mm; border: 1px solid #e2e7f1; }
    .parties b.role { display: block; font-size: 7.5pt; letter-spacing: 0.08em; text-transform: uppercase; color: #5a6682; margin-bottom: 1mm; }
    .blank { display: inline-block; border-bottom: 1px solid #8a94ab; width: 46mm; height: 3.6mm; }
    table { width: 100%; border-collapse: collapse; }
    .items th { text-align: left; font-size: 8pt; color: #5a6682; border-bottom: 1px solid #c9d1e0; padding: 1.6mm 1.5mm; }
    .items td { border-bottom: 1px solid #eef1f6; padding: 1.8mm 1.5mm; vertical-align: top; }
    .items small { color: #5a6682; }
    .num, .items th.num { text-align: right; white-space: nowrap; }
    .block { page-break-inside: avoid; }
    .totals td { padding: 1.2mm 1.5mm; }
    .totals .grand td { font-weight: bold; font-size: 11pt; border-top: 1px solid #c9d1e0; padding-top: 2mm; }
    .clauses p { margin-bottom: 1.8mm; }
    .clauses b { color: #0f1a33; }
    .signatures { margin-top: 4mm; }
    .signatures td { width: 50%; padding: 11mm 4mm 0; text-align: center; vertical-align: top; font-size: 8.5pt; }
    .signatures .line { border-top: 1px solid #1b2233; padding-top: 1.5mm; }
</style>
</head>
<body>
<header>
    <div class="brand">loqfy<span>.</span></div>
    <div class="meta">Contrato de locação nº {{ $rental->id }}<br>Emitido em {{ $date($issuedAt) }}</div>
</header>
<footer>
    {{ $company['name'] }} · Contrato de locação nº {{ $rental->id }} · Página <span class="page"></span>
    <br>Modelo de contrato gerado pelo Loqfy. Revise as cláusulas com um advogado antes de usá-lo com seus clientes.
</footer>

<h1>CONTRATO DE LOCAÇÃO DE EQUIPAMENTOS</h1>

<table class="parties">
    <tr>
        <td>
            <b class="role">Locadora</b>
            <b>{{ $company['name'] }}</b><br>
            CNPJ: {!! $company['cnpj'] ? e($company['cnpj']) : '<span class="blank"></span>' !!}<br>
            {{ $company['address'] }}
            @if ($company['phone'])<br>Telefone: {{ $company['phone'] }}@endif
        </td>
        <td>
            <b class="role">Locatário</b>
            <b>{{ $customer?->name ?? $rental->customer_name ?? '' }}</b><br>
            @if ($isCompany)
                CNPJ: {!! $customer->cnpj ? e($cnpj($customer->cnpj)) : '<span class="blank"></span>' !!}<br>
                Representante: <span class="blank"></span><br>
                CPF do representante: <span class="blank" style="width: 32mm"></span><br>
            @else
                CPF: <span class="blank"></span><br>
                Documento de identidade: <span class="blank"></span><br>
            @endif
            Endereço: {!! $customer?->address ? e($customer->address) : '<span class="blank" style="width: 60mm"></span>' !!}<br>
            Telefone: {!! $customer?->phone ? e($customer->phone) : '<span class="blank"></span>' !!}
            @if ($customer?->email)<br>E-mail: {{ $customer->email }}@endif
        </td>
    </tr>
</table>

<p style="margin-top: 4mm">As partes acima identificadas celebram o presente contrato de locação de bens móveis, que se rege pelas cláusulas a seguir e pelos artigos 565 a 578 do Código Civil.</p>

<h2>1. Objeto e período</h2>
<p>A LOCADORA entrega ao LOCATÁRIO, em locação, os equipamentos abaixo, pelo período de <b>{{ $date($rental->start_date) }} a {{ $date($rental->end_date) }}</b> ({{ $days }} {{ $days === 1 ? 'diária' : 'diárias' }}){{ $deliveryPlace ? ', com entrega em '.$deliveryPlace : ', com retirada e devolução na loja da LOCADORA' }}.</p>

<table class="items">
    <thead>
        <tr><th>Equipamento</th><th class="num">Qtd.</th><th class="num">Diária</th><th class="num">Valor</th></tr>
    </thead>
    <tbody>
    @foreach ($lines as $line)
        <tr>
            <td>{{ $line['name'] }}@if ($line['description'])<br><small>{{ $line['description'] }}</small>@endif</td>
            <td class="num">{{ $line['quantity'] }}</td>
            <td class="num">{{ $money($line['daily_rate']) }}</td>
            <td class="num">{{ $money($line['total']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals" style="width: 62%; margin-left: 38%; margin-top: 2mm">
    <tr><td>Subtotal</td><td class="num">{{ $money($subtotal) }}</td></tr>
    @if ($discount > 0)<tr><td>Desconto por período</td><td class="num">− {{ $money($discount) }}</td></tr>@endif
    @if ($deliveryFee > 0)<tr><td>Entrega e retirada</td><td class="num">{{ $money($deliveryFee) }}</td></tr>@endif
    <tr class="grand"><td>Valor total da locação</td><td class="num">{{ $money($total) }}</td></tr>
</table>

<div class="clauses">
    <div class="block">
    <h2>2. Pagamento</h2>
    <p>O LOCATÁRIO pagará à LOCADORA o valor total de <b>{{ $money($total) }}</b> até a data de retirada ou entrega dos equipamentos, salvo outra condição combinada por escrito.</p>
    </div>

    <div class="block">
    <h2>3. Entrega, vistoria e devolução</h2>
    <p><b>3.1.</b> Os equipamentos são entregues em perfeito estado de funcionamento, conferido em vistoria de saída na presença do LOCATÁRIO ou de quem o represente, que poderá ser registrada por fotografia.</p>
    <p><b>3.2.</b> O LOCATÁRIO devolverá os equipamentos até {{ $date($rental->end_date) }}, limpos e no estado em que os recebeu, ressalvado o desgaste natural do uso adequado. Na devolução será feita nova vistoria, comparada à de saída.</p>
    <p><b>3.3.</b> Cada dia de atraso na devolução será cobrado à razão de {{ $lateFee }} do equipamento por dia, sem prejuízo das demais obrigações deste contrato.</p>
    </div>

    <div class="block">
    <h2>4. Uso e responsabilidade</h2>
    <p><b>4.1.</b> O LOCATÁRIO usará os equipamentos apenas para o fim a que se destinam, por pessoas habilitadas e conforme as instruções do fabricante, e não poderá sublocá-los, emprestá-los nem retirá-los do local informado sem autorização da LOCADORA.</p>
    <p><b>4.2.</b> O LOCATÁRIO responde pela guarda dos equipamentos desde a entrega até a devolução, inclusive em caso de furto, roubo ou dano, e ressarcirá à LOCADORA o valor do conserto ou, se não houver conserto, o valor de reposição.</p>
    <p><b>4.3.</b> Em caso de defeito, o LOCATÁRIO interromperá o uso e avisará a LOCADORA, sendo vedado qualquer reparo por conta própria.</p>
    </div>

    <div class="block">
    <h2>5. Disposições finais</h2>
    <p><b>5.1.</b> A falta de pagamento ou o descumprimento de qualquer cláusula permite à LOCADORA rescindir o contrato e retirar os equipamentos onde estiverem.</p>
    <p><b>5.2.</b> As partes elegem o foro da comarca de {{ $company['city'] }} para resolver qualquer questão deste contrato.</p>
    </div>
</div>

<div class="block">
<p style="margin-top: 4mm">E, por estarem de acordo, assinam o presente em duas vias de igual teor.</p>
<p>{{ $company['city'] }}, {{ $issuedAt->day }} de {{ $months[$issuedAt->month - 1] }} de {{ $issuedAt->year }}.</p>

<table class="signatures">
    <tr>
        <td><div class="line">{{ $company['name'] }}<br>LOCADORA</div></td>
        <td><div class="line">{{ $customer?->name ?? $rental->customer_name ?? '' }}<br>LOCATÁRIO</div></td>
    </tr>
    <tr>
        <td><div class="line">Testemunha<br>Nome e CPF:</div></td>
        <td><div class="line">Testemunha<br>Nome e CPF:</div></td>
    </tr>
</table>
</div>
</body>
</html>
