import * as React from 'react'
import { useTranslation } from 'react-i18next'

type Props = {
  // a new value (the address of another page) clears the error, so the menu still leads somewhere
  resetKey: string
  children: React.ReactNode
}

type State = { error: Error | null }

// Catches an error thrown while a page is drawn: the header and the menu stay, with a message, instead of a blank
// screen. React still needs a class for this; no hook catches the errors of the components below. (React itself
// writes the error to the console.)
export class ErrorBoundary extends React.Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidUpdate(previous: Props) {
    if (previous.resetKey !== this.props.resetKey && this.state.error !== null) {
      this.setState({ error: null })
    }
  }

  render() {
    return this.state.error === null ? this.props.children : <PageFailed />
  }
}

function PageFailed() {
  const { t } = useTranslation()

  return (
    <div role="alert" className="space-y-2">
      <h1 className="text-xl font-semibold text-slate-900 dark:text-slate-100">{t('app.page_failed_title')}</h1>
      <p className="text-slate-500 dark:text-slate-400">{t('app.page_failed')}</p>
    </div>
  )
}
