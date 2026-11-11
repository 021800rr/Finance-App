import * as React from 'react'

type Props = {
  title?: string
  // the first box of a page carries the title of the page (h1), the boxes under it an h2
  heading?: 'h1' | 'h2'
  // what stands right of the title, e.g. a button
  aside?: React.ReactNode
  children: React.ReactNode
}

const TITLE = {
  h1: 'text-xl font-semibold text-slate-900 dark:text-slate-100',
  h2: 'text-lg font-semibold text-slate-900 dark:text-slate-100',
}

// The box every page puts its content in (white; dark in the dark style), with its title. What is in it is spaced
// evenly, so the pieces inside need no margins of their own.
export function Card({ title, heading = 'h1', aside, children }: Props) {
  const Heading = heading

  return (
    <section className="space-y-4 rounded-xl bg-white dark:bg-slate-900 p-6 shadow">
      {title !== undefined && (
        <div className="flex items-center justify-between gap-4">
          <Heading className={TITLE[heading]}>{title}</Heading>
          {aside}
        </div>
      )}
      {children}
    </section>
  )
}
