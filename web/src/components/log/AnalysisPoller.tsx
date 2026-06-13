"use client";
import { useEffect, useRef } from "react";
import { useRouter } from "next/navigation";

export function AnalysisPoller({ id, ready }: { id: string; ready: boolean }) {
  const router = useRouter();
  const attempts = useRef(0);
  useEffect(() => {
    if (ready) return;
    let active = true;
    let timer: ReturnType<typeof setTimeout>;
    const poll = async () => {
      if (!active) return;
      attempts.current += 1;
      try {
        const res = await fetch(`/api/${id}`, { cache: "no-store" });
        if (res.ok) {
          const data = await res.json();
          if (data.analysis) { router.refresh(); return; }
        }
      } catch {}
      if (active && attempts.current < 40) timer = setTimeout(poll, 1500);
    };
    timer = setTimeout(poll, 1500);
    return () => { active = false; clearTimeout(timer); };
  }, [id, ready, router]);
  return null;
}
