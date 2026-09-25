interface HeroProps {
  tag: string;
  title: string;
  sub: string;
}

export default function Hero({ tag, title, sub }: HeroProps) {
  return (
    <section className="hero">
      <div className="hero-blob hero-blob--1" aria-hidden="true" />
      <div className="hero-blob hero-blob--2" aria-hidden="true" />
      <div className="hero-content">
        <span className="hero-tag">{tag}</span>
        <h1>{title}</h1>
        <p>{sub}</p>
      </div>
    </section>
  );
}