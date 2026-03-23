/**
 * 安全的HTML过滤函数
 * 只允许白名单中的标签和属性
 */

const ALLOWED_TAGS = ['div', 'p', 'span', 'strong', 'em', 'a', 'br']

const ALLOWED_ATTRIBUTES = {
  a: ['href', 'rel', 'target']
}

/**
 * 过滤HTML，只保留安全的标签和属性
 * @param {string} html - 原始HTML字符串
 * @returns {string} - 过滤后的HTML字符串
 */
export function sanitizeHtml(html) {
  if (!html || typeof html !== 'string') {
    return ''
  }

  // 创建一个临时的DOM元素来解析HTML
  const div = document.createElement('div')
  div.innerHTML = html

  // 递归清理元素
  function cleanElement(element) {
    // 检查标签是否在白名单中
    if (element.nodeType === Node.ELEMENT_NODE) {
      const tagName = element.tagName.toLowerCase()

      if (!ALLOWED_TAGS.includes(tagName)) {
        // 移除不允许的标签，但保留其子节点
        while (element.firstChild) {
          element.parentNode.insertBefore(element.firstChild, element)
        }
        element.parentNode.removeChild(element)
        return
      }

      // 清理属性
      const allowedAttrs = ALLOWED_ATTRIBUTES[tagName] || []
      const attrsToRemove = []

      for (let i = 0; i < element.attributes.length; i++) {
        const attr = element.attributes[i]
        if (!allowedAttrs.includes(attr.name)) {
          attrsToRemove.push(attr.name)
        }
      }

      attrsToRemove.forEach(attrName => {
        element.removeAttribute(attrName)
      })

      // 为a标签添加安全属性
      if (tagName === 'a') {
        const href = element.getAttribute('href')
        if (href && !href.startsWith('#')) {
          element.setAttribute('rel', 'noopener noreferrer')
          element.setAttribute('target', '_blank')
        }
      }
    }

    // 递归处理子节点
    const children = Array.from(element.childNodes)
    children.forEach(child => cleanElement(child))
  }

  // 从body开始清理（div.innerHTML会自动包含在body中）
  Array.from(div.childNodes).forEach(child => cleanElement(child))

  return div.innerHTML
}