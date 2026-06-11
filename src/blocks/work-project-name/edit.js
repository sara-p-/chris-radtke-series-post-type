import { __ } from '@wordpress/i18n'
import { useBlockProps } from '@wordpress/block-editor'
import { useSelect } from '@wordpress/data'
import { store as coreStore } from '@wordpress/core-data'
import { Spinner, Notice } from '@wordpress/components'

export default function Edit({ context }) {
  const { postId } = context

  const { terms, isLoading } = useSelect(
    (select) => {
      if (!postId) {
        return { terms: null, isLoading: false }
      }

      const query = {
        post: postId,
        per_page: 100,
        _fields: 'id,name,link',
      }

      return {
        terms: select(coreStore).getEntityRecords(
          'taxonomy',
          'projects',
          query,
        ),
        isLoading: !select(coreStore).hasFinishedResolution(
          'getEntityRecords',
          ['taxonomy', 'projects', query],
        ),
      }
    },
    [postId],
  )

  const blockProps = useBlockProps({ className: 'editor-work-projects' })

  // No post context (e.g. bare template view).
  if (!postId) {
    return (
      <div {...blockProps}>
        <h6>Project Name Placeholder</h6>
        {/* <Notice status='info' isDismissible={false}>
          {__('Projects will display when viewing a Work post.', 'work')}
        </Notice> */}
      </div>
    )
  }

  if (isLoading) {
    return (
      <div {...blockProps}>
        <Spinner />
      </div>
    )
  }

  if (!terms?.length) {
    return (
      <div {...blockProps}>
        <Notice status='info' isDismissible={false}>
          {__('No projects assigned to this post.', 'work')}
        </Notice>
      </div>
    )
  }

  return (
    <ul {...blockProps}>
      {terms.map((term) => (
        <li key={term.id} className='work-projects__item'>
          {term.name}
        </li>
      ))}
    </ul>
  )
}
