import { createContext, useContext, useEffect } from 'react';

export function useScrollLock(locked: boolean): void {
  useEffect(() => {
    if (!locked) return;
    const isDesktop = window.matchMedia('(min-width: 769px)').matches;
    const html = document.documentElement;
    const body = document.body;
    const scroller = document.querySelector<HTMLElement>('.main .page-content');
    const prevHtmlOverflow = html.style.overflow;
    const prevBodyOverflow = body.style.overflow;
    const prevHtmlOverscroll = html.style.overscrollBehavior;
    const prevBodyOverscroll = body.style.overscrollBehavior;
    const prevScrollerOverflow = scroller ? scroller.style.overflow : '';
    const prevBodyPosition = body.style.position;
    const prevBodyTop = body.style.top;
    const prevBodyLeft = body.style.left;
    const prevBodyRight = body.style.right;
    const prevBodyWidth = body.style.width;
    const scrollY = window.scrollY;

    html.style.overflow = 'hidden';
    body.style.overflow = 'hidden';
    html.style.overscrollBehavior = 'contain';
    body.style.overscrollBehavior = 'contain';
    if (scroller) scroller.style.overflow = 'hidden';

    // iOS Safari ignores `overflow: hidden` on <body> — pin the body at its
    // current scroll position so the page behind a modal/drawer can't scroll
    // through (which made bottom sheets unusable on iPhones).
    if (!isDesktop) {
      body.style.position = 'fixed';
      body.style.top = `-${scrollY}px`;
      body.style.left = '0';
      body.style.right = '0';
      body.style.width = '100%';
    }

    return () => {
      html.style.overflow = prevHtmlOverflow;
      body.style.overflow = prevBodyOverflow;
      html.style.overscrollBehavior = prevHtmlOverscroll;
      body.style.overscrollBehavior = prevBodyOverscroll;
      if (scroller) scroller.style.overflow = prevScrollerOverflow;
      if (!isDesktop) {
        body.style.position = prevBodyPosition;
        body.style.top = prevBodyTop;
        body.style.left = prevBodyLeft;
        body.style.right = prevBodyRight;
        body.style.width = prevBodyWidth;
        window.scrollTo(0, scrollY);
      }
    };
  }, [locked]);
}

export interface ToastHandle {
  showToast: (msg: string, type?: 'success' | 'error') => void;
}

export const ToastCtx = createContext<ToastHandle>({ showToast: () => {} });

export const useToast = (): ToastHandle => useContext(ToastCtx);