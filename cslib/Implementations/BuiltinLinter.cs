#if FALSE
using System.IO;
using ICSharpCode.NRefactory6.CSharp;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace cslib
{
    public class BuiltinLinter : ILinter
    {
        public void Process(LintResults results)
        {
            SyntaxTree tree;
            using (var reader = new StreamReader(results.FileName))
            {
                tree = CSharpSyntaxTree.ParseText(reader.ReadToEnd());
            }

            // Apply policies
            tree.AcceptVisitor(new EnsureClassNameMatchesFileNamePolicy(results));
            tree.AcceptVisitor(new EnsureNoNestedPublicClassesPolicy(results));
            tree.AcceptVisitor(new EnsureOnePublicClassPerFilePolicy(results));
            tree.AcceptVisitor(new UseImplicitVariableTypeInDeclarationPolicy(results));
            tree.AcceptVisitor(new ConsoleWriteUsedOutsideOfProgramMainPolicy(results));
        }
    }
}

#endif