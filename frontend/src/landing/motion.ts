import gsap from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import Lenis from 'lenis'
import 'lenis/dist/lenis.css'

gsap.registerPlugin(ScrollTrigger)

export interface Motion {
  scrollTo: (target: HTMLElement) => void
  cleanup: () => void
}

const number = (decimals: number) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })

function countUp(element: HTMLElement, timeline?: gsap.core.Timeline, position?: gsap.Position) {
  const target = Number(element.dataset.count)
  const format = number(Number(element.dataset.decimals ?? 0))
  const state = { value: 0 }
  const tween = { value: target, duration: 1.2, ease: 'power2.out', onUpdate: () => (element.textContent = format.format(state.value)) }

  return timeline ? timeline.to(state, { ...tween, ease: 'none' }, position) : gsap.to(state, tween)
}

export function initMotion(root: HTMLElement): Motion {
  const all = <T extends HTMLElement = HTMLElement>(selector: string) => gsap.utils.toArray<T>(root.querySelectorAll<T>(selector))
  const one = <T extends HTMLElement = HTMLElement>(selector: string) => root.querySelector<T>(selector)
  const media = gsap.matchMedia()
  let lenis: Lenis | undefined

  media.add({ wide: '(min-width: 901px)', narrow: '(max-width: 900px)' }, (context) => {
    const wide = Boolean(context.conditions?.wide)
    const tick = (time: number) => lenis?.raf(time * 1000)

    if (wide) {
      lenis = new Lenis({ lerp: 0.09, wheelMultiplier: 0.9 })
      lenis.on('scroll', ScrollTrigger.update)
      gsap.ticker.add(tick)
      gsap.ticker.lagSmoothing(0)
    }

    const windowElement = one('[data-window]')
    const rest = wide ? 12 : 0

    gsap
      .timeline({ defaults: { ease: 'power4.out' }, delay: 0.1 })
      .from(one('[data-nav]'), { y: -20, autoAlpha: 0, duration: 0.8 })
      .from(all('.lp-hero .lp-word'), { yPercent: 115, duration: 0.95, stagger: 0.045 }, 0.05)
      .from(all('[data-hero-item]'), { y: 24, autoAlpha: 0, duration: 0.9, stagger: 0.08 }, 0.35)
      .fromTo(windowElement, { y: 90, rotateX: rest + 12, scale: 0.95, autoAlpha: 0 }, { y: 0, rotateX: rest, scale: 1, autoAlpha: 1, duration: 1.3, ease: 'power3.out' }, 0.45)
      .from(all('[data-bar]'), { scaleY: 0, duration: 0.9, stagger: 0.06 }, 0.9)

    if (wide) {
      gsap.fromTo(windowElement, { rotateX: rest }, { rotateX: 0, ease: 'none', immediateRender: false, scrollTrigger: { trigger: one('[data-hero]'), start: 'top top', end: 'bottom 60%', scrub: 1 } })
      gsap.to(one('[data-glow]'), { y: 140, ease: 'none', scrollTrigger: { trigger: one('[data-hero]'), start: 'top top', end: 'bottom top', scrub: 1 } })
    }

    gsap.fromTo(
      all('.lp-read'),
      { opacity: 0.14 },
      { opacity: 1, ease: 'none', stagger: 0.5, scrollTrigger: { trigger: one('[data-manifesto]'), start: 'top 72%', end: 'bottom 58%', scrub: 0.6 } },
    )

    const scene = one('[data-scene]')
    const total = one('[data-quote] [data-count]')
    const story = gsap.timeline({
      defaults: { ease: wide ? 'none' : 'power3.out' },
      scrollTrigger: wide
        ? { trigger: scene, start: 'top top', end: '+=150%', scrub: 1, pin: true, anticipatePin: 1 }
        : { trigger: one('[data-bubble]'), start: 'top 80%', once: true },
    })

    story
      .from(one('[data-bubble]'), { y: 40, autoAlpha: 0, duration: 0.8 })
      .fromTo(all('[data-mark]'), { '--mark': 0 }, { '--mark': 0.24, duration: 0.4, stagger: 0.25 })
      .from(one('[data-quote]'), { y: 60, scale: 0.96, autoAlpha: 0, duration: 0.8 }, '+=0.1')
      .from(all('[data-quote-row]'), { x: 24, autoAlpha: 0, duration: 0.5, stagger: 0.22 })
      .from(one('[data-quote-pill]'), { scale: 0.85, autoAlpha: 0, duration: 0.3 }, '-=0.2')

    if (total) countUp(total, story, '<-0.4')

    const scan = one('[data-scan]')
    if (scan) {
      gsap
        .timeline({ scrollTrigger: { trigger: scan, start: 'top 75%', once: true } })
        .fromTo(one('[data-scanline]'), { x: 0, opacity: 1 }, { x: () => scan.offsetWidth, duration: 1.3, ease: 'power2.inOut' })
        .to(one('[data-scanline]'), { opacity: 0, duration: 0.2 })
        .from(one('[data-marker]'), { scale: 0.6, autoAlpha: 0, duration: 0.5, ease: 'power3.out' }, '-=0.5')
        .from(one('[data-finding]'), { y: 12, autoAlpha: 0, duration: 0.5, ease: 'power3.out' }, '-=0.2')
    }

    gsap.from(all('[data-insight]'), { y: 24, autoAlpha: 0, duration: 0.8, ease: 'power4.out', stagger: 0.12, scrollTrigger: { trigger: one('.lp-insights'), start: 'top 80%', once: true } })

    all('[data-reveal]').forEach((element) => {
      gsap.from(element, { y: 48, autoAlpha: 0, duration: 1, ease: 'power4.out', scrollTrigger: { trigger: element, start: 'top 84%', once: true } })
    })

    all('[data-stagger]').forEach((section) => {
      const heading = section.querySelector('.lp-h2, .lp-eyebrow')
      const items = section.querySelectorAll('[data-stagger-item]')
      const trigger = { trigger: section, start: 'top 76%', once: true }

      gsap.from(heading, { y: 30, autoAlpha: 0, duration: 0.9, ease: 'power4.out', scrollTrigger: trigger })
      gsap.from(items, { y: 36, autoAlpha: 0, duration: 0.9, ease: 'power4.out', stagger: 0.09, delay: 0.1, scrollTrigger: trigger })
    })

    all('.lp-numbers [data-count]').forEach((element) => {
      ScrollTrigger.create({ trigger: element, start: 'top 82%', once: true, onEnter: () => countUp(element) })
    })

    root.classList.add('lp--ready')

    return () => {
      gsap.ticker.remove(tick)
      lenis?.destroy()
      lenis = undefined
    }
  })

  document.fonts.ready.then(() => ScrollTrigger.refresh())

  return {
    scrollTo: (target) => (lenis ? lenis.scrollTo(target, { offset: -40 }) : target.scrollIntoView({ behavior: 'smooth' })),
    cleanup: () => media.revert(),
  }
}
