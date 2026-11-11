import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation, useSearchParams } from 'react-router'
import { describe, expect, it } from 'vitest'
import { Pagination } from './Pagination'

function show(page: number, lastPage: number) {
  function Page() {
    const [params] = useSearchParams()
    const location = useLocation()

    return <>
      <output>{location.search}</output>
      <Pagination page={Number(params.get('page'))} lastPage={lastPage} />
    </>
  }
  return render(
    <MemoryRouter initialEntries={[`/?page=${page}&q=rent&highlight=7`]}>
      <Page />
    </MemoryRouter>,
  )
}

describe('Pagination', () => {
  it('offers ten pages, the last page and a jump to the next group', async () => {
    show(1, 25)
    const navigation = screen.getByRole('navigation')
    expect(navigation.querySelectorAll('[aria-current="page"]')).toHaveLength(1)
    expect(screen.getByLabelText('Strona 10')).toBeInTheDocument()
    expect(screen.queryByLabelText('Strona 12')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Strona 25')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('link', { name: 'Strona 11' }))

    expect(screen.getByRole('status')).toHaveTextContent('?page=11&q=rent&highlight=7')
    expect(screen.getByLabelText('Strona 20')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Strona 21' })).toHaveTextContent('…')
    expect(screen.getByTitle('Strona 1')).toHaveTextContent('…')
  })

  it('redirects past the last page while preserving the other parameters', async () => {
    show(26, 25)

    expect(await screen.findByRole('status')).toHaveTextContent('?page=25&q=rent&highlight=7')
    expect(screen.getByLabelText('Strona 25')).toHaveAttribute('aria-current', 'page')
  })

  it('hides the controls for an empty list and redirects to page one', async () => {
    show(2, 0)

    expect(await screen.findByRole('status')).toHaveTextContent('?page=1&q=rent&highlight=7')
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument()
  })
})
