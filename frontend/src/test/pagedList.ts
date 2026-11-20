// A page of a list as the API answers it (PageNormalizer): the rows in "content" and the numbers of the page.
export function pagedList<T>(content: T[], totalElements: number, totalPages = 1, number = 1) {
  return { content, page: { size: 25, number, totalElements, totalPages } }
}
