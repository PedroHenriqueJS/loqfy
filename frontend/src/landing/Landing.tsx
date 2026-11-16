import { Fragment, useEffect, useLayoutEffect, useRef } from 'react'
import type { Motion } from './motion'
import '@fontsource-variable/gabarito'
import '@fontsource-variable/wix-madefor-text'
import './landing.css'

function Words({ text, className }: { text: string; className: string }) {
  return (
    <>
      <span className="lp-sr">{text}</span>
      <span aria-hidden="true">
        {text.split(' ').map((word, index) => (
          <Fragment key={index}>
            <span className="lp-mask"><span className={className}>{word}</span></span>{' '}
          </Fragment>
        ))}
      </span>
    </>
  )
}

const BARS = [16.3, 24.7, 18.1, 15.5, 17.8, 10.4]
const MONTHS = ['jun', 'jul', 'ago', 'set', 'out', 'nov']

const MODULES = [
  ['Orçamentos', 'Monte à mão ou revise o que a IA preparou. Preço, desconto por período e frete saem do seu cadastro.'],
  ['Locações', 'Saída, devolução e avarias de cada equipamento, com o estoque sempre conferido por período.'],
  ['Catálogo', 'Equipamentos, diárias, unidades e os apelidos que os seus clientes usam.'],
  ['Clientes', 'Quem aluga, com o histórico de orçamentos e locações de cada um.'],
  ['Financeiro', 'Fluxo de caixa, contas a receber e a pagar. Orçamento aprovado já entra como conta a receber.'],
  ['Painel', 'Receita, ocupação do estoque, devoluções do dia e o que está parado há meses.'],
]

export default function Landing() {
  const root = useRef<HTMLDivElement>(null)

  const motion = useRef<Motion | null>(null)

  useLayoutEffect(() => {
    const element = root.current
    if (!element || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    element.classList.add('lp--motion')
    const fallback = window.setTimeout(() => element.classList.add('lp--ready'), 2500)
    let cancelled = false

    import('./motion').then(({ initMotion }) => {
      if (!cancelled) motion.current = initMotion(element)
    })

    return () => {
      cancelled = true
      window.clearTimeout(fallback)
      motion.current?.cleanup()
      motion.current = null
      element.classList.remove('lp--motion', 'lp--ready')
    }
  }, [])

  useEffect(() => {
    const element = root.current
    if (!element) return

    const onClick = (event: MouseEvent) => {
      const link = (event.target as HTMLElement).closest<HTMLAnchorElement>('a[data-scroll]')
      const target = link && document.getElementById(link.getAttribute('href')!.slice(1))
      if (!target) return

      event.preventDefault()
      if (motion.current) motion.current.scrollTo(target)
      else target.scrollIntoView()
    }

    element.addEventListener('click', onClick)
    return () => element.removeEventListener('click', onClick)
  }, [])

  return (
    <div className="lp" ref={root}>
      <header className="lp-nav" data-nav>
        <a href="#/" aria-label="Loqfy"><img src="/logo-dark.png" alt="Loqfy" width={104} /></a>
        <nav aria-label="Seções">
          <a href="#ia" data-scroll>Ferramentas de IA</a>
          <a href="#numeros" data-scroll>Números</a>
          <a href="#sistema" data-scroll>O sistema</a>
        </nav>
        <a className="lp-btn lp-btn--small" href="#/entrar">Entrar</a>
      </header>

      <main>
        <section className="lp-hero" data-hero>
          <div className="lp-glow lp-glow--warm" data-glow />
          <div className="lp-glow lp-glow--cool" />

          <div className="lp-hero__copy">
            <p className="lp-eyebrow" data-hero-item>Sistema para locadoras de máquinas e ferramentas</p>
            <h1 className="lp-h1">
              <Words text="O pedido chega bagunçado." className="lp-word" />
              <br />
              <span className="lp-accent"><Words text="O orçamento sai pronto." className="lp-word" /></span>
            </h1>
            <p className="lp-lead" data-hero-item>
              O Loqfy cuida de orçamento, locação, estoque e caixa da sua locadora. E usa IA nas tarefas que mais tomam tempo: ler a mensagem do cliente e conferir a devolução.
            </p>
            <div className="lp-actions" data-hero-item>
              <a className="lp-btn" href="#/entrar">Entrar na demonstração</a>
              <a className="lp-link" href="#ia" data-scroll>Ver como funciona</a>
            </div>
            <p className="lp-fine" data-hero-item>Sem cadastro e sem senha. Dados de exemplo.</p>
          </div>

          <div className="lp-stage">
            <div className="lp-window" data-window aria-hidden="true">
              <div className="lp-window__bar"><i /><i /><i /></div>
              <div className="lp-app">
                <aside className="lp-app__side">
                  <img src="/logo-dark.png" alt="" width={62} />
                  {['Início', 'Orçamentos', 'Locações', 'Clientes', 'Catálogo', 'Financeiro'].map((item, index) => (
                    <span key={item} className={index === 0 ? 'is-active' : undefined}>{item}</span>
                  ))}
                  <em>Ferramentas de IA</em>
                  <span className="is-ai">Orçamento por mensagem</span>
                  <span className="is-ai">Vistoria por foto</span>
                </aside>
                <div className="lp-app__main">
                  <div className="lp-app__ai"><b>Análise da IA</b><span>Caixa positivo, uma devolução atrasada e estoque com folga para o fim de semana.</span></div>
                  <div className="lp-app__stats">
                    {[['Receita no mês', 'R$ 10.363'], ['Ocupação do estoque', '19,1%'], ['Locações com clientes', '5'], ['A receber em atraso', 'R$ 1.015']].map(([label, value]) => (
                      <div key={label}><span>{label}</span><b>{value}</b></div>
                    ))}
                  </div>
                  <div className="lp-app__row">
                    <div className="lp-app__chart">
                      <span>Receita por mês</span>
                      <div className="lp-bars">
                        {BARS.map((value, index) => (
                          <div key={index}><i data-bar style={{ height: `${(value / 26) * 100}%` }} /><small>{MONTHS[index]}</small></div>
                        ))}
                      </div>
                    </div>
                    <div className="lp-app__list">
                      <span>Devoluções</span>
                      {[['Construtora Ilha Norte', 'Atrasada', true], ['João Pedreira', 'hoje', false], ['Marcenaria Trindade', 'sex., 13/11', false]].map(([name, when, late]) => (
                        <p key={name as string}><b>{name}</b><i className={late ? 'is-late' : undefined}>{when}</i></p>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="lp-scene" id="ia" data-scene>
          <div className="lp-scene__copy">
            <p className="lp-tag">Ferramenta de IA</p>
            <h2 className="lp-h2">Cole a mensagem. Revise o orçamento.</h2>
            <p>
              A IA entende gíria, erro de digitação e data solta como “fim de semana”. Ela encontra os equipamentos no seu catálogo e confere o estoque no período. Preço, desconto e frete são calculados pelo sistema, com os valores que você cadastrou.
            </p>
            <ul className="lp-list">
              <li>Faltou estoque? Ela sugere um equipamento equivalente e avisa.</li>
              <li>Pedido incompleto? Fica marcado para revisão, sem chute.</li>
            </ul>
          </div>

          <div className="lp-scene__visual" aria-hidden="true">
            <div className="lp-bubble" data-bubble>
              Boa tarde! Preciso de <mark data-mark>uma betoneira</mark> e <mark data-mark>20 andaimes</mark> pro <mark data-mark>fim de semana</mark>, entrega no <mark data-mark>Campeche</mark>. Aqui é o Marcos
              <small>WhatsApp · 13:36</small>
            </div>

            <div className="lp-quote" data-quote>
              <header><b>Orçamento para Marcos</b><i className="lp-pill lp-pill--amber" data-quote-pill>Revisar</i></header>
              <div className="lp-quote__row" data-quote-row>
                <span><b>1 × Betoneira 150L</b><i className="lp-pill lp-pill--amber">No lugar da 400L, sem estoque</i></span><em>R$ 110,00</em>
              </div>
              <div className="lp-quote__row" data-quote-row>
                <span><b>20 × Andaime tubular</b><i className="lp-pill lp-pill--red">Só 10 livres no período</i></span><em>R$ 320,00</em>
              </div>
              <div className="lp-quote__row lp-quote__row--muted" data-quote-row>
                <span>sáb., 14/11 a dom., 15/11 · 2 diárias</span><em />
              </div>
              <div className="lp-quote__row lp-quote__row--muted" data-quote-row>
                <span>Entrega no Campeche</span><em>R$ 110,00</em>
              </div>
              <footer data-quote-row><span>Total</span><b>R$ <span data-count="540">540</span>,00</b></footer>
            </div>
          </div>
        </section>

        <section className="lp-pair">
          <article className="lp-feature" data-reveal>
            <p className="lp-tag">Ferramenta de IA</p>
            <h2 className="lp-h3">Duas fotos. A diferença aparece.</h2>
            <p>
              Fotografe o equipamento na saída e na devolução. A IA compara as duas imagens e aponta possíveis avarias novas, como amassado, peça faltando ou cabo cortado. Sujeira de obra e desgaste antigo ficam de fora.
            </p>
            <div className="lp-photos" aria-hidden="true">
              <figure><img src="/mark-dark.png" alt="" /><figcaption>Saída</figcaption></figure>
              <figure data-scan>
                <img src="/mark-dark.png" alt="" />
                <i className="lp-scanline" data-scanline />
                <i className="lp-marker" data-marker />
                <figcaption>Devolução</figcaption>
              </figure>
            </div>
            <p className="lp-finding" data-finding aria-hidden="true"><i className="lp-pill lp-pill--amber">Possível avaria</i> Amassado na lateral do tambor</p>
            <p className="lp-fine">A IA aponta. Quem decide a cobrança é você.</p>
          </article>

          <article className="lp-feature" data-reveal>
            <p className="lp-tag">Ferramenta de IA</p>
            <h2 className="lp-h3">Pergunte aos números o que merece atenção.</h2>
            <p>
              No painel, um botão pede à IA uma leitura dos indicadores do dia. Ela devolve riscos, oportunidades e o que fazer na semana. Cada número citado é conferido contra os seus dados.
            </p>
            <div className="lp-insights" aria-hidden="true">
              <div data-insight><i className="lp-pill lp-pill--red">Risco</i><b>Devolução atrasada</b><span>2 betoneiras com a Construtora Ilha Norte desde terça.</span></div>
              <div data-insight><i className="lp-pill lp-pill--green">Oportunidade</i><b>Receita acima do mês passado</b><span>R$ 10.363 até hoje, contra R$ 8.309 no mesmo período.</span></div>
              <div data-insight><i className="lp-pill lp-pill--blue">Para saber</i><b>Cinco equipamentos parados</b><span>Sem nenhuma locação nos últimos 90 dias.</span></div>
            </div>
          </article>
        </section>

        <section className="lp-rules" data-stagger>
          <h2 className="lp-h2">A IA trabalha. A decisão continua sua.</h2>
          <div className="lp-rules__grid">
            <div data-stagger-item><span>01</span><h3>A IA lê</h3><p>Texto livre e fotos, que planilha nenhuma entende.</p></div>
            <div data-stagger-item><span>02</span><h3>O sistema calcula</h3><p>Preço, estoque e frete saem do seu cadastro. A IA nunca vê nem escreve um valor.</p></div>
            <div data-stagger-item><span>03</span><h3>Você aprova</h3><p>Nenhum orçamento sai e nenhuma avaria é cobrada sem o seu ok.</p></div>
          </div>
        </section>

        <section className="lp-numbers" id="numeros" data-stagger>
          <p className="lp-eyebrow">Os números do nosso teste</p>
          <div className="lp-numbers__grid">
            <div data-stagger-item><b><span data-count="24">24</span> de 24</b><p>pedidos lidos sem nenhum erro</p></div>
            <div data-stagger-item><b><span data-count="2.5" data-decimals="1">2,5</span> s</b><p>em média para montar um orçamento</p></div>
            <div data-stagger-item><b><span data-count="0">0</span></b><p>preços inventados</p></div>
          </div>
          <p className="lp-fine">Teste com 24 mensagens de exemplo, incluindo gíria, erro de digitação, item sem estoque e pedido incompleto.</p>
        </section>

        <section className="lp-system" id="sistema" data-stagger>
          <div className="lp-system__head">
            <h2 className="lp-h2">E funciona inteiro sem IA.</h2>
            <p>Tudo o que a IA faz, você também faz à mão, nas mesmas telas. Ela é um atalho para quando o balcão está cheio.</p>
          </div>
          <div className="lp-system__grid">
            {MODULES.map(([title, text]) => (
              <div key={title} className="lp-card" data-stagger-item><h3>{title}</h3><p>{text}</p></div>
            ))}
          </div>
        </section>

        <section className="lp-final" data-reveal>
          <div className="lp-glow lp-glow--warm" />
          <h2 className="lp-h1">Veja funcionando.</h2>
          <p className="lp-lead">A demonstração abre com uma locadora de exemplo já em operação.</p>
          <a className="lp-btn" href="#/entrar">Entrar na demonstração</a>
        </section>
      </main>

      <footer className="lp-footer">
        <img src="/logo-dark.png" alt="Loqfy" width={84} />
        <span>Sistema para locadoras de máquinas e ferramentas</span>
      </footer>
    </div>
  )
}
