import * as React from 'react'

// An account number in its grey frame, in a typewriter font, as in the web app.
export function Code({ children }: { children: React.ReactNode }) {
  return <code className="rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5">{children}</code>
}
